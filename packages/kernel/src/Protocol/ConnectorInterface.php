<?php

namespace Erikwang2013\IndustrialProtocols\Protocol;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;

interface ConnectorInterface
{
    public function connect(): void;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function read(string|array $points): array;
    public function write(string|array $points, array $values): array;
    public function getHealth(): HealthStatus;
}
