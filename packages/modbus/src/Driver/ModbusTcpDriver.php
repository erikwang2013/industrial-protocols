<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Modbus\Driver;

use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;
use Erikwang2013\IndustrialProtocols\Modbus\Exception\ModbusException;
use Erikwang2013\IndustrialProtocols\Modbus\Frame\ModbusResponse;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class ModbusTcpDriver implements DriverInterface
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
        if ($this->socket !== null) {
            return;
        }
        $this->socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno, $errstr, $this->timeout,
        );
        if (!$this->socket) {
            throw new ConnectionTimeoutException(
                "Failed to connect to {$this->host}:{$this->port}: [$errno] $errstr",
                ['host' => $this->host, 'port' => $this->port],
            );
        }
        stream_set_timeout($this->socket, (int)$this->timeout, (int)(($this->timeout - (int)$this->timeout) * 1e6));
    }

    public function disconnect(): void
    {
        if ($this->socket) { fclose($this->socket); $this->socket = null; }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$this->socket) throw new ModbusException('Not connected');

        $requestBytes = $frame->toBytes();
        $start = microtime(true);
        fwrite($this->socket, $requestBytes);

        $header = @fread($this->socket, 7);
        if ($header === false || strlen($header) < 7) {
            if (stream_get_meta_data($this->socket)['timed_out']) {
                throw new ConnectionTimeoutException('Read timeout');
            }
            throw new ModbusException('Failed to read response header');
        }

        $length = unpack('n', substr($header, 4, 2))[1];
        $remaining = @fread($this->socket, $length - 1);
        if ($remaining === false) throw new ModbusException('Failed to read response body');

        $this->lastLatency = (microtime(true) - $start) * 1000;
        return ModbusResponse::fromBytes($header . $remaining);
    }

    public function sendAsync(FrameInterface $frame): mixed { return $this->send($frame); }
    public function getLatency(): float { return $this->lastLatency; }
    public function supportsAsync(): bool { return false; }
}
