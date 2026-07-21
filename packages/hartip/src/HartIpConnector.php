<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\HartIp;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\HartIp\Driver\HartIpDriver;
use Erikwang2013\IndustrialProtocols\HartIp\Frame\HartIpFrame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class HartIpConnector implements ConnectorInterface
{
    private HartIpDriver $driver;
    private int $messageId = 0;

    public function __construct(private array $config)
    {
        $this->driver = new HartIpDriver(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? HartIpDriver::DEFAULT_PORT,
            ($config['timeout'] ?? 5000) / 1000.0,
        );
    }

    public function connect(): void { $this->driver->connect(); }
    public function disconnect(): void { $this->driver->disconnect(); }
    public function isConnected(): bool { return $this->driver->isConnected(); }

    public function read(string|array $points): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($addresses as $address) {
            $command = $this->parseReadCommand($address);
            $hartCmd = $this->buildHartCommand($command);
            $frame = new HartIpFrame(HartIpFrame::TYPE_REQUEST, 0, ++$this->messageId, $hartCmd);
            $response = $this->driver->send($frame);
            $results[$address] = $response->getData();
        }

        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($addresses as $i => $address) {
            $command = $this->parseWriteCommand($address);
            $value = is_array($values) ? ($values[$address] ?? $values[$i] ?? '') : $values;
            $hartCmd = $this->buildHartCommand($command, is_string($value) ? $value : pack('G', (float) $value));
            $frame = new HartIpFrame(HartIpFrame::TYPE_REQUEST, 0, ++$this->messageId, $hartCmd);
            $response = $this->driver->send($frame);
            $results[$address] = $response->getData();
        }

        return $results;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) {
            return HealthStatus::closed('Not connected');
        }
        return HealthStatus::healthy($this->driver->getLatency());
    }

    /**
     * Send a HART command over IP.
     */
    public function command(int $command, string $data = ''): HartIpFrame
    {
        $hartCmd = $this->buildHartCommand($command, $data);
        $frame = new HartIpFrame(HartIpFrame::TYPE_REQUEST, 0, ++$this->messageId, $hartCmd);
        return $this->driver->send($frame);
    }

    /**
     * Build a minimal HART command frame.
     * Full HART frame: preamble(5-20 0xFF) + delimiter(0x82) + address(1-5) + command(1) + dataLen(1) + data + checksum
     */
    private function buildHartCommand(int $command, string $data = ''): string
    {
        $pollAddr = $this->config['polling_address'] ?? 0;

        // Minimal HART short-frame: preamble + delimiter + address + command + dataLen + data + checksum
        $preamble = str_repeat(chr(0xFF), 5);
        $delimiter = chr(0x82);  // Master to slave, short frame
        $address = chr($pollAddr & 0x3F);  // Short address
        $cmd = chr($command & 0xFF);
        $dataLen = chr(strlen($data) & 0xFF);

        $frame = $preamble . $delimiter . $address . $cmd . $dataLen . $data;

        // HART XOR checksum over delimiter through end of data
        $checksum = 0;
        for ($i = 5; $i < strlen($frame); $i++) {  // Start after preamble
            $checksum ^= ord($frame[$i]);
        }
        $frame .= chr($checksum);

        return $frame;
    }

    private function parseReadCommand(string $address): int
    {
        return match ($address) {
            'pv', 'primary_variable' => 1,       // Read primary variable
            'loop_current', 'current' => 2,       // Read loop current
            'device_info', 'info' => 13,          // Read tag, descriptor, date
            'unique_id' => 0,                     // Read unique identifier
            'dynamic_vars' => 3,                  // Read dynamic variables
            'range_values' => 15,                 // Read range values
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
            'pv_units' => 44,
            default => (int) $address,
        };
    }
}
