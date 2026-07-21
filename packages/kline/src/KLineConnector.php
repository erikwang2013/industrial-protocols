<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\KLine;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\KLine\Driver\KLineDriver;
use Erikwang2013\IndustrialProtocols\KLine\Frame\KLineFrame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class KLineConnector implements ConnectorInterface
{
    private KLineDriver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new KLineDriver(
            $config['device'] ?? '/dev/ttyUSB0',
            $config['baud_rate'] ?? 10400,
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
        $targetAddr = $this->config['target_address'] ?? 0x33;
        $sourceAddr = $this->config['source_address'] ?? 0xF1;

        foreach ($addresses as $address) {
            $serviceId = $this->parseServiceId($address);
            $frame = new KLineFrame($targetAddr, $sourceAddr, [$serviceId], KLineFrame::ADDR_PHYSICAL);
            $response = $this->driver->send($frame);
            $results[$address] = $response->getData();
        }

        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        $targetAddr = $this->config['target_address'] ?? 0x33;
        $sourceAddr = $this->config['source_address'] ?? 0xF1;

        foreach ($addresses as $i => $address) {
            $serviceId = $this->parseServiceId($address);
            $value = is_array($values) ? ($values[$address] ?? $values[$i] ?? 0) : $values;
            $data = is_array($value) ? $value : [$value & 0xFF];
            $payload = array_merge([$serviceId], $data);
            $frame = new KLineFrame($targetAddr, $sourceAddr, $payload, KLineFrame::ADDR_PHYSICAL);
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
     * Send a raw diagnostic service request.
     */
    public function diagnostics(int $serviceId, array $parameters = []): KLineFrame
    {
        $targetAddr = $this->config['target_address'] ?? 0x33;
        $sourceAddr = $this->config['source_address'] ?? 0xF1;
        $payload = array_merge([$serviceId], $parameters);

        $frame = new KLineFrame($targetAddr, $sourceAddr, $payload, KLineFrame::ADDR_PHYSICAL);
        return $this->driver->send($frame);
    }

    private function parseServiceId(string $address): int
    {
        return match ($address) {
            'ecu_identification', 'vin', 'id' => 0x09,         // Service 0x09: Request vehicle info
            'current_data', 'live_data', 'data' => 0x01,       // Service 0x01: Current powertrain data
            'dtc', 'fault_codes', 'errors' => 0x03,            // Service 0x03: Emission-related DTCs
            'clear_dtc', 'clear' => 0x04,                      // Service 0x04: Clear DTCs
            'freeze_frame' => 0x02,                            // Service 0x02: Freeze frame data
            'oxygen_sensor' => 0x05,                           // Service 0x05: Oxygen sensor data
            default => (int) $address,
        };
    }
}
