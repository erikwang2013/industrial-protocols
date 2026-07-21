<?php

namespace Erikwang2013\IndustrialProtocols\Dnp3;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Dnp3\Driver\Dnp3Driver;
use Erikwang2013\IndustrialProtocols\Dnp3\Frame\Dnp3Frame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

/**
 * DNP3 Connector for power utility automation.
 *
 * Address formats:
 *   '0:60:1'    => Class 0, Variation 60 (Analog Input), Index 1
 *   '1:1:0'     => Group 1 (Binary Input), Variation 1, Index 0
 *   'class0'    => Class 0 poll (all static data)
 *   '10:2:3'    => Group 10 (Binary Output), Variation 2, Index 3
 *
 * Write uses select-before-operate pattern.
 */
class Dnp3Connector implements ConnectorInterface
{
    private Dnp3Driver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new Dnp3Driver($config);
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
        $addresses = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($addresses as $addr) {
            [$group, $variation, $index] = $this->parseAddress($addr);
            $frame = Dnp3Frame::readRequest($group, $variation, $index);
            $response = $this->driver->send($frame);
            $results[$addr] = $response->getData();
        }

        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($addresses as $i => $addr) {
            $value = is_array($values) ? ($values[$addr] ?? $values[$i] ?? 0) : (int) $values;
            [$group, $variation, $index] = $this->parseAddress($addr);

            // Select-before-operate
            $selectFrame = Dnp3Frame::select($group, $variation, $index, $value);
            $this->driver->send($selectFrame);

            $operateFrame = Dnp3Frame::operate($group, $variation, $index, $value);
            $response = $this->driver->send($operateFrame);
            $results[$addr] = $response->getData();
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
     * Direct operate a point (no select needed).
     */
    public function directOperate(int $group, int $variation, int $index, int $value): array
    {
        $frame = Dnp3Frame::directOperate($group, $variation, $index, $value);
        $response = $this->driver->send($frame);
        return $response->getData();
    }

    /**
     * Execute a Class 0 poll (read all static data).
     */
    public function class0Poll(): array
    {
        $frame = Dnp3Frame::readRequest(
            Dnp3Frame::GROUP_CLASS_OBJECT,
            0,
            0,
        );
        $response = $this->driver->send($frame);
        return $response->getData();
    }

    /**
     * Get the underlying driver.
     */
    public function getDriver(): Dnp3Driver
    {
        return $this->driver;
    }

    /**
     * Parse address string like "30:1:5" => [group, variation, index].
     * "class0" => [60, 0, 0] (Class 0 poll).
     */
    private function parseAddress(string $address): array
    {
        if ($address === 'class0') {
            return [Dnp3Frame::GROUP_CLASS_OBJECT, 0, 0];
        }

        $parts = explode(':', $address);
        return [
            (int) ($parts[0] ?? Dnp3Frame::GROUP_CLASS_OBJECT),
            (int) ($parts[1] ?? 1),
            (int) ($parts[2] ?? 0),
        ];
    }
}
