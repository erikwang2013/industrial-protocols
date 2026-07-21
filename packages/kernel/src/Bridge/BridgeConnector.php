<?php

namespace Erikwang2013\IndustrialProtocols\Bridge;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

/**
 * Generic Connector that delegates to a hardware bridge.
 * Used for protocols that require dedicated hardware.
 */
class BridgeConnector implements ConnectorInterface
{
    public function __construct(
        private BridgeInterface $bridge,
        private string $protocolName,
    ) {}

    public function connect(): void
    {
        $this->bridge->open();
    }

    public function disconnect(): void
    {
        $this->bridge->close();
    }

    public function isConnected(): bool
    {
        return $this->bridge->isReady();
    }

    public function read(string|array $points): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $addr) {
            $response = $this->bridge->execute('read', ['address' => $addr]);
            $results[$addr] = $response;
        }
        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $i => $addr) {
            $value = $values[$addr] ?? $values[$i] ?? null;
            $response = $this->bridge->execute('write', ['address' => $addr, 'value' => $value]);
            $results[$addr] = $response;
        }
        return $results;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->bridge->isReady()) {
            return HealthStatus::closed('Bridge not ready');
        }
        return HealthStatus::healthy(0.0);
    }

    /**
     * Execute a raw command on the bridge.
     */
    public function command(string $cmd, string|array $data = ''): string
    {
        return $this->bridge->execute($cmd, $data);
    }

    public function getBridge(): BridgeInterface
    {
        return $this->bridge;
    }
}
