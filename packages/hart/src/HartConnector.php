<?php

namespace Erikwang2013\IndustrialProtocols\Hart;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Exception\AddressOutOfRangeException;
use Erikwang2013\IndustrialProtocols\Hart\Driver\HartDriver;
use Erikwang2013\IndustrialProtocols\Hart\Frame\HartFrame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class HartConnector implements ConnectorInterface
{
    private HartDriver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new HartDriver(
            $config['device'] ?? '/dev/ttyUSB0',
            $config['baud_rate'] ?? 1200,
            ($config['timeout'] ?? 5000) / 1000.0,
            $config['preamble_count'] ?? 5,
        );
    }

    public function connect(): void { $this->driver->connect(); }
    public function disconnect(): void { $this->driver->disconnect(); }
    public function isConnected(): bool { return $this->driver->isConnected(); }

    public function read(string|array $points): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        $pollAddr = $this->config['polling_address'] ?? 0;

        foreach ($addresses as $address) {
            $cmd = $this->parseReadCommand($address);
            $frame = HartFrame::universalCommand($pollAddr, $cmd);
            $response = $this->driver->send($frame);

            if ($cmd === HartFrame::CMD_READ_PV) {
                $results[$address] = $response->getPV();
            } elseif ($cmd === HartFrame::CMD_READ_LOOP_CURRENT) {
                $results[$address] = $response->getLoopCurrent();
            } elseif ($cmd === HartFrame::CMD_READ_DEVICE_INFO) {
                $results[$address] = $response->getFieldDeviceInfo();
            } else {
                $results[$address] = $response->getData();
            }
        }
        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        $pollAddr = $this->config['polling_address'] ?? 0;

        foreach ($addresses as $i => $address) {
            $value = is_array($values) ? ($values[$address] ?? $values[$i] ?? null) : $values;
            $cmd = $this->parseWriteCommand($address);
            $data = is_string($value) ? $value : pack('G', (float) $value);
            $frame = HartFrame::universalCommand($pollAddr, $cmd, $data);
            $response = $this->driver->send($frame);
            $results[$address] = $response->getData();
        }
        return $results;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) return HealthStatus::closed('Not connected');
        return HealthStatus::healthy($this->driver->getLatency());
    }

    /**
     * Send a raw HART command directly.
     */
    public function command(int $cmd, string $data = ''): HartFrame
    {
        $pollAddr = $this->config['polling_address'] ?? 0;
        $frame = HartFrame::universalCommand($pollAddr, $cmd, $data);
        return $this->driver->send($frame);
    }

    private function parseReadCommand(string $address): int
    {
        return match ($address) {
            'pv', 'primary_variable' => HartFrame::CMD_READ_PV,
            'loop_current', 'current' => HartFrame::CMD_READ_LOOP_CURRENT,
            'device_info', 'info' => HartFrame::CMD_READ_DEVICE_INFO,
            'unique_id' => 0,
            'dynamic_vars' => HartFrame::CMD_READ_DYNAMIC_VARS,
            default => (int) $address,
        };
    }

    private function parseWriteCommand(string $address): int
    {
        return match ($address) {
            'message' => 17,
            'tag' => 18,
            'date' => 20,
            'range_values' => 35,
            default => (int) $address,
        };
    }
}
