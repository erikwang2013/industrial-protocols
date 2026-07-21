<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Bacnet\Driver;

use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class BacnetDriver implements DriverInterface
{
    private $socket = null;
    private float $lastLatency = 0.0;

    public function __construct(
        private string $host,
        private int $port = 47808,
        private float $timeout = 3.0,
    ) {}

    public function connect(): void
    {
        $this->socket = stream_socket_client("udp://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout);
        if (!$this->socket) {
            throw new \RuntimeException("BACnet connect failed: [$errno] $errstr");
        }
        stream_set_timeout($this->socket, (int)$this->timeout);
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$this->socket) {
            throw new \RuntimeException('Not connected');
        }
        $start = microtime(true);
        fwrite($this->socket, $frame->toBytes());
        $response = fread($this->socket, 4096);
        $this->lastLatency = (microtime(true) - $start) * 1000;
        if ($response === false || $response === '') {
            throw new \RuntimeException('BACnet response timeout');
        }
        return $frame::fromBytes($response);
    }

    public function sendAsync(FrameInterface $frame): mixed
    {
        return $this->send($frame);
    }

    public function getLatency(): float
    {
        return $this->lastLatency;
    }

    public function supportsAsync(): bool
    {
        return false;
    }
}
