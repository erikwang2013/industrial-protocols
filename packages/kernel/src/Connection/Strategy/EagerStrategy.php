<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Connection\Strategy;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class EagerStrategy implements StrategyInterface
{
    /** @var array<string, ConnectorInterface> */
    private array $connections = [];

    public function getOrCreate(string $deviceId, callable $factory): ConnectorInterface
    {
        if (!isset($this->connections[$deviceId])) {
            $connector = $factory();
            $connector->connect();
            $this->connections[$deviceId] = $connector;
        }
        return $this->connections[$deviceId];
    }

    public function disconnect(string $deviceId): void
    {
        if (isset($this->connections[$deviceId])) {
            $this->connections[$deviceId]->disconnect();
            unset($this->connections[$deviceId]);
        }
    }

    public function disconnectAll(): void
    {
        foreach ($this->connections as $id => $connector) {
            $connector->disconnect();
            unset($this->connections[$id]);
        }
    }

    public function getActiveConnections(): array
    {
        return $this->connections;
    }
}
