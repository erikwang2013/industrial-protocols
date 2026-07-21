<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Connection\Strategy;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class PooledStrategy implements StrategyInterface
{
    /** @var array<string, array<ConnectorInterface>> */
    private array $pools = [];

    /** @var array<string, int> */
    private array $nextIndex = [];

    /** @var array<string, ConnectorInterface> */
    private array $activeConnections = [];

    public function __construct(private int $poolSize = 4) {}

    public function getOrCreate(string $deviceId, callable $factory): ConnectorInterface
    {
        if (!isset($this->pools[$deviceId])) {
            $this->pools[$deviceId] = [];
            for ($i = 0; $i < $this->poolSize; $i++) {
                $connector = $factory();
                $connector->connect();
                $this->pools[$deviceId][] = $connector;
            }
            $this->nextIndex[$deviceId] = 0;
        }

        $index = $this->nextIndex[$deviceId];
        $this->nextIndex[$deviceId] = ($index + 1) % $this->poolSize;

        return $this->pools[$deviceId][$index];
    }

    public function disconnect(string $deviceId): void
    {
        if (isset($this->pools[$deviceId])) {
            foreach ($this->pools[$deviceId] as $connector) {
                $connector->disconnect();
            }
            unset($this->pools[$deviceId]);
            unset($this->nextIndex[$deviceId]);
        }
    }

    public function disconnectAll(): void
    {
        foreach (array_keys($this->pools) as $deviceId) {
            $this->disconnect($deviceId);
        }
    }

    public function getActiveConnections(): array
    {
        $result = [];
        foreach ($this->pools as $deviceId => $pool) {
            if (!empty($pool)) {
                $result[$deviceId] = $pool[0];
            }
        }
        return $result;
    }
}
