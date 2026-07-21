<?php

namespace Erikwang2013\IndustrialProtocols\EtherNetIP;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\EtherNetIP\Driver\EtherNetIPDriver;
use Erikwang2013\IndustrialProtocols\EtherNetIP\Frame\EtherNetIPFrame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class EtherNetIPConnector implements ConnectorInterface
{
    private EtherNetIPDriver $driver;
    private int $sessionHandle = 0;

    public function __construct(private array $config)
    {
        $this->driver = new EtherNetIPDriver(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 44818,
            ($config['timeout'] ?? 3000) / 1000.0,
        );
    }

    public function connect(): void
    {
        $this->driver->connect();
        // Register EIP session
        $regFrame = EtherNetIPFrame::registerSession();
        $response = $this->driver->send($regFrame);
        $data = $response->getData();
        $this->sessionHandle = $data['session_handle'] ?? 0;
    }

    public function disconnect(): void
    {
        if ($this->sessionHandle > 0) {
            $unreg = EtherNetIPFrame::unregisterSession($this->sessionHandle);
            $this->driver->send($unreg);
        }
        $this->driver->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->driver->isConnected();
    }

    public function read(string|array $points): array
    {
        $results = [];
        $addresses = is_array($points) ? $points : [$points];
        foreach ($addresses as $addr) {
            $frame = EtherNetIPFrame::readTag($this->sessionHandle, $addr);
            $response = $this->driver->send($frame);
            $results[$addr] = $response->getData();
        }
        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        return [];
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) {
            return HealthStatus::closed('Not connected');
        }
        return HealthStatus::healthy($this->driver->getLatency());
    }
}
