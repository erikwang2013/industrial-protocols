<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\HartIp\Driver;

use Erikwang2013\IndustrialProtocols\HartIp\Exception\HartIpException;
use Erikwang2013\IndustrialProtocols\HartIp\Frame\HartIpFrame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * HART-IP driver over TCP stream.
 * Connects to HART-IP server on port 5094 (default).
 */
class HartIpDriver implements DriverInterface
{
    public const DEFAULT_PORT = 5094;

    /** @var resource|null */
    private $socket = null;
    private float $latency = 0.0;
    private int $seqNum = 0;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = self::DEFAULT_PORT,
        private float $timeout = 5.0,
    ) {}

    public function connect(): void
    {
        $this->socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if (!$this->socket) {
            throw HartIpException::connectionFailed($this->host, $this->port);
        }

        stream_set_timeout($this->socket, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1e6));
        stream_set_blocking($this->socket, true);
    }

    public function disconnect(): void
    {
        if ($this->socket && is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null && is_resource($this->socket);
    }

    /**
     * Send a HART-IP frame and receive the response.
     */
    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$frame instanceof HartIpFrame) {
            throw new \InvalidArgumentException('HartIpDriver expects HartIpFrame');
        }

        $start = microtime(true);

        // Increment sequence number and assign
        $this->seqNum++;
        $wireFrame = new HartIpFrame(
            $frame->getMessageType(),
            $this->seqNum,
            $frame->getMessageId(),
            $frame->getHartCommand(),
        );

        fwrite($this->socket, $wireFrame->toBytes());

        // Read response header (8 bytes)
        $header = '';
        while (strlen($header) < 8) {
            $chunk = fread($this->socket, 8 - strlen($header));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $header .= $chunk;
        }

        if (strlen($header) < 8) {
            throw new \RuntimeException('HART-IP: incomplete response header');
        }

        $unpacked = unpack('Cversion/Ctype/Cstatus/Creserved/nsequence/nmessageId/Clength', $header);
        $payloadLen = $unpacked['length'];

        // Read payload
        $payload = '';
        while (strlen($payload) < $payloadLen) {
            $chunk = fread($this->socket, $payloadLen - strlen($payload));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
        }

        $this->latency = microtime(true) - $start;

        $response = new HartIpFrame(
            $unpacked['type'],
            $unpacked['sequence'],
            $unpacked['messageId'],
            $payload,
            $unpacked['status'],
        );

        if ($response->isError()) {
            throw new \RuntimeException(sprintf('HART-IP error response: status 0x%02X', $unpacked['status']));
        }

        return $response;
    }

    public function sendAsync(FrameInterface $frame): mixed
    {
        throw new \RuntimeException('HART-IP async not yet implemented');
    }

    public function getLatency(): float { return $this->latency; }
    public function supportsAsync(): bool { return false; }
}
