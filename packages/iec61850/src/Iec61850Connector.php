<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Iec61850;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Iec61850\Driver\Iec61850Driver;
use Erikwang2013\IndustrialProtocols\Iec61850\Frame\Iec61850Frame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

/**
 * IEC 61850 MMS Connector.
 *
 * Uses IED data path names for addressing:
 *   "IED1/MMXU1.MX.A.phsA"     => Phase A current measurement
 *   "IED1/MMXU1.MX.PPV.phsAB"  => Phase-to-phase voltage AB
 *   "IED1/XCBR1.ST.Pos.stVal"  => Circuit breaker position
 *   "IED1/LLN0.OR.Health.stVal" => Device health status
 */
class Iec61850Connector implements ConnectorInterface
{
    private Iec61850Driver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new Iec61850Driver($config);
    }

    public function connect(): void
    {
        $this->driver->connect();
    }

    public function disconnect(): void
    {
        $this->driver->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->driver->isConnected();
    }

    public function read(string|array $points): array
    {
        $paths = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($paths as $path) {
            $frame = Iec61850Frame::readRequest($path);
            $response = $this->driver->send($frame);
            $results[$path] = $response->getData();
        }

        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $paths = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($paths as $i => $path) {
            $value = is_array($values) ? ($values[$path] ?? $values[$i] ?? null) : $values;
            $frame = Iec61850Frame::writeRequest($path, $value);
            $response = $this->driver->send($frame);
            $results[$path] = $response->getData();
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
     * Get the underlying driver.
     */
    public function getDriver(): Iec61850Driver
    {
        return $this->driver;
    }
}
