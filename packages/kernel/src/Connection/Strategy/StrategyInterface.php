<?php

namespace Erikwang2013\IndustrialProtocols\Connection\Strategy;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

interface StrategyInterface
{
    public function getOrCreate(string $deviceId, callable $factory): ConnectorInterface;
    public function disconnect(string $deviceId): void;
    public function disconnectAll(): void;
    public function getActiveConnections(): array;
}
