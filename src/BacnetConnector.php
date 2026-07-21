<?php

namespace IndustrialProtocols\Bacnet;

use IndustrialProtocols\Connection\HealthStatus;
use IndustrialProtocols\Bacnet\Driver\BacnetDriver;
use IndustrialProtocols\Bacnet\Frame\BacnetFrame;
use IndustrialProtocols\Protocol\ConnectorInterface;

class BacnetConnector implements ConnectorInterface
{
    private BacnetDriver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new BacnetDriver(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 47808,
            ($config['timeout'] ?? 3000) / 1000.0,
        );
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
        $results = [];
        $addresses = is_array($points) ? $points : [$points];
        foreach ($addresses as $addr) {
            $parts = explode(':', $addr);
            $objType = (int)($parts[0] ?? 0);
            $objInst = (int)($parts[1] ?? 0);
            $propId  = (int)($parts[2] ?? 85); // default: present-value
            $deviceId = (int)($this->config['device_id'] ?? 1);

            $request = BacnetFrame::readProperty($deviceId, $objType, $objInst, $propId);
            $response = $this->driver->send($request);
            $results[$addr] = $response->getData();
        }
        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        return []; // WriteProperty not implemented in stub
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) {
            return HealthStatus::closed('Not connected');
        }
        return HealthStatus::healthy($this->driver->getLatency());
    }

    public function discoverDevices(int $timeoutSec = 5): array
    {
        $request = BacnetFrame::whoIs();
        $this->driver->send($request);

        $devices = [];
        $deadline = time() + $timeoutSec;
        while (time() < $deadline) {
            $response = @fread($this->driver->socket ?? STDIN, 4096);
            if ($response !== false && $response !== '') {
                $frame = BacnetFrame::fromBytes($response);
                $devices[] = $frame->getData();
            }
            usleep(100000);
        }
        return $devices;
    }
}
