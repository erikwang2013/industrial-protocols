<?php

namespace Erikwang2013\IndustrialProtocols\Profinet;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Profinet\Driver\ProfinetDriver;
use Erikwang2013\IndustrialProtocols\Profinet\Frame\ProfinetFrame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class ProfinetConnector implements ConnectorInterface
{
    private ProfinetDriver $driver;

    public function __construct(private array $config) {}

    public function connect(): void
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 34964;
        $this->driver = new ProfinetDriver(
            $host,
            $port,
            ($this->config['timeout'] ?? 5000) / 1000.0,
            $this->config['transport'] ?? 'udp',
        );
        $this->driver->connect();
    }

    public function disconnect(): void
    {
        if (isset($this->driver)) {
            $this->driver->disconnect();
        }
    }

    public function isConnected(): bool
    {
        return isset($this->driver) && $this->driver->isConnected();
    }

    public function read(string|array $points): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $addr) {
            // Parse: "api:slot:subslot:index"
            $parts = explode(':', $addr);
            $api     = (int)($parts[0] ?? 0);
            $slot    = (int)($parts[1] ?? 0);
            $subslot = (int)($parts[2] ?? 1);
            $index   = (int)($parts[3] ?? 0);

            $request = ProfinetFrame::readRecord($api, $slot, $subslot, $index);
            $response = $this->driver->send($request);
            $results[$addr] = $response->getData();
        }
        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $i => $addr) {
            $parts = explode(':', $addr);
            $value = is_array($values) ? ($values[$addr] ?? $values[$i] ?? '') : $values;
            $request = ProfinetFrame::writeRecord(
                (int)($parts[0] ?? 0), (int)($parts[1] ?? 0),
                (int)($parts[2] ?? 1), (int)($parts[3] ?? 0),
                is_string($value) ? $value : pack('V', (int)$value),
            );
            $response = $this->driver->send($request);
            $results[$addr] = $response->getData();
        }
        return $results;
    }

    /**
     * Discover Profinet devices on the network via DCP Identify.
     */
    public function discoverDevices(int $timeoutSec = 5): array
    {
        $request = ProfinetFrame::dcpIdentify();
        // Broadcast to all devices
        $sock = stream_socket_client("udp://255.255.255.255:34964", $errno, $errstr, 2);
        if (!$sock) {
            throw new \RuntimeException("Broadcast failed: $errstr");
        }
        stream_set_blocking($sock, false);
        fwrite($sock, $request->toBytes());

        $devices = [];
        $deadline = time() + $timeoutSec;
        while (time() < $deadline) {
            $response = @fread($sock, 4096);
            if ($response !== false && $response !== '') {
                $frame = ProfinetFrame::fromBytes($response);
                foreach ($frame->getBlocks() as $block) {
                    if ($block['option'] === 2 && $block['suboption'] === 2) {
                        $devices[] = [
                            'name'        => trim($block['blockData']),
                            'ip'          => null,
                            'raw_blocks'  => $frame->getBlocks(),
                        ];
                    }
                }
            }
            usleep(100000);
        }
        fclose($sock);
        return $devices;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) {
            return HealthStatus::closed('Not connected');
        }
        return HealthStatus::healthy(0.0);
    }
}
