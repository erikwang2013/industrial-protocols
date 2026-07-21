<?php

namespace IndustrialProtocols\Connection;

use IndustrialProtocols\Config\ConfigRepositoryInterface;
use IndustrialProtocols\Connection\Strategy\StrategyInterface;
use IndustrialProtocols\Coroutine\CoroutineAdapterInterface;
use IndustrialProtocols\Event\ConnectionConnectedEvent;
use IndustrialProtocols\Event\ConnectionDisconnectedEvent;
use IndustrialProtocols\Log\LogDriverInterface;
use IndustrialProtocols\Protocol\ConnectorInterface;
use IndustrialProtocols\Protocol\ProtocolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ConnectionManager
{
    /**
     * @param array<string, ProtocolInterface> $protocols
     */
    public function __construct(
        private array $protocols,
        private ConfigRepositoryInterface $configRepo,
        private EventDispatcherInterface $eventDispatcher,
        private CoroutineAdapterInterface $coroutine,
        private LogDriverInterface $log,
        private StrategyInterface $strategy,
    ) {}

    public function connect(string $deviceId): ConnectorInterface
    {
        $config = $this->configRepo->getDeviceConfig($deviceId);
        $protocolName = $config['protocol'];

        if (!isset($this->protocols[$protocolName])) {
            throw new \RuntimeException("Protocol not found: $protocolName");
        }

        return $this->strategy->getOrCreate($deviceId, function () use ($deviceId, $protocolName, $config) {
            $connector = $this->protocols[$protocolName]->createConnector($config);
            $connector->connect();

            $this->eventDispatcher->dispatch(new ConnectionConnectedEvent(
                $deviceId, $protocolName,
                ($config['host'] ?? '') . ':' . ($config['port'] ?? ''),
            ));
            $this->log->log('INFO', "Device $deviceId connected ($protocolName)");

            return $connector;
        });
    }

    public function disconnect(string $deviceId): void
    {
        $connector = $this->getConnection($deviceId);
        if ($connector) {
            $connector->disconnect();
            $this->strategy->disconnect($deviceId);
            $this->eventDispatcher->dispatch(new ConnectionDisconnectedEvent($deviceId));
            $this->log->log('INFO', "Device $deviceId disconnected");
        }
    }

    public function getConnection(string $deviceId): ?ConnectorInterface
    {
        return $this->strategy->getActiveConnections()[$deviceId] ?? null;
    }

    public function getAllConnections(): array
    {
        return $this->strategy->getActiveConnections();
    }

    public function health(string $deviceId): HealthStatus
    {
        $connector = $this->getConnection($deviceId);
        return $connector?->getHealth() ?? HealthStatus::closed('Not connected');
    }

    public function healthAll(): array
    {
        $results = [];
        foreach ($this->getAllConnections() as $deviceId => $connector) {
            $results[$deviceId] = $connector->getHealth();
        }
        return $results;
    }

    public function shutdown(): void
    {
        $this->strategy->disconnectAll();
    }
}
