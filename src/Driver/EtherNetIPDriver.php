<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\EtherNetIP\Driver;

use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class EtherNetIPDriver implements DriverInterface
{
    private $socket = null;
    private float $lastLatency = 0.0;

    public function __construct(
        private string $host,
        private int $port,
        private float $timeout = 3.0,
    ) {}

    public function connect(): void
    {
        $this->socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );
        if (!$this->socket) {
            throw new \RuntimeException("EIP connect failed: [$errno] $errstr");
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
        $start = microtime(true);
        fwrite($this->socket, $frame->toBytes());
        $header = fread($this->socket, 24); // ENIP header is 24 bytes
        $length = unpack('v', substr($header, 2, 2))[1];
        $body = fread($this->socket, $length - 24);
        $this->lastLatency = (microtime(true) - $start) * 1000;
        return $frame::fromBytes($header . $body);
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
