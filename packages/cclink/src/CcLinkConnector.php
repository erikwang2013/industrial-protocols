<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\CcLink;

use Erikwang2013\IndustrialProtocols\CcLink\Driver\CcLinkDriver;
use Erikwang2013\IndustrialProtocols\CcLink\Frame\CcLinkFrame;
use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class CcLinkConnector implements ConnectorInterface
{
    private CcLinkDriver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new CcLinkDriver(
            $config['device'] ?? '/dev/ttyUSB0',
            $config['baud_rate'] ?? 156000,
            ($config['timeout'] ?? 3000) / 1000.0,
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
            $stationNo = $this->config['station_no'] ?? 0;
            // Cyclic read: send empty data to request station data
            $frame = CcLinkFrame::cyclic($stationNo, chr(0x01)); // read request
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
            $value = is_array($values) ? ($values[$address] ?? $values[$i] ?? null) : $values;
            $stationNo = $this->config['station_no'] ?? 0;
            $data = is_string($value) ? $value : pack('v', (int) $value);
            $frame = CcLinkFrame::cyclic($stationNo, chr(0x02) . $data); // write request
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
}
