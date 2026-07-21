<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Lin;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Lin\Driver\LinDriver;
use Erikwang2013\IndustrialProtocols\Lin\Frame\LinFrame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class LinConnector implements ConnectorInterface
{
    private LinDriver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new LinDriver(
            $config['device'] ?? '/dev/ttyUSB0',
            $config['baud_rate'] ?? 19200,
            ($config['timeout'] ?? 1000) / 1000.0,
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
            $frameId = $this->parseAddress($address);
            $frame = new LinFrame($frameId);
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
            $frameId = $this->parseAddress($address);
            $value = is_array($values) ? ($values[$address] ?? $values[$i] ?? 0) : $values;
            $data = is_array($value) ? $value : [$value & 0xFF];
            $frame = new LinFrame($frameId, $data);
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
     * Send a raw LIN frame by ID.
     */
    public function sendFrame(int $frameId, array $data = []): LinFrame
    {
        $frame = new LinFrame($frameId, $data);
        return $this->driver->send($frame);
    }

    private function parseAddress(string $address): int
    {
        return match ($address) {
            'diagnostic_master' => 60,
            'diagnostic_slave' => 61,
            default => (int) $address,
        };
    }
}
