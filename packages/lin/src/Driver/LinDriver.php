<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Lin\Driver;

use Erikwang2013\IndustrialProtocols\Lin\Frame\LinFrame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * LIN bus driver over serial UART.
 * LIN operates at 19200 bps, single-wire (plus ground).
 */
class LinDriver implements DriverInterface
{
    /** @var resource|null */
    private $serial = null;
    private float $latency = 0.0;

    public function __construct(
        private string $device = '/dev/ttyUSB0',
        private int $baudRate = 19200,
        private float $timeout = 1.0,
    ) {}

    public function connect(): void
    {
        $this->serial = @fopen($this->device, 'r+b');
        if (!$this->serial) {
            throw new \RuntimeException("Cannot open LIN serial device: {$this->device}");
        }
        stream_set_timeout($this->serial, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1e6));
        stream_set_blocking($this->serial, true);

        // Configure serial port for LIN: 8N1, 19200 bps
        exec(sprintf('stty -F %s %d cs8 -cstopb -parenb 2>/dev/null', escapeshellarg($this->device), $this->baudRate));
    }

    public function disconnect(): void
    {
        if ($this->serial && is_resource($this->serial)) {
            fclose($this->serial);
            $this->serial = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->serial !== null && is_resource($this->serial);
    }

    /**
     * Send a LIN frame and receive the response.
     * LIN is a command/response protocol: master sends header, slave responds with data.
     */
    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$frame instanceof LinFrame) {
            throw new \InvalidArgumentException('LinDriver expects LinFrame');
        }

        $start = microtime(true);

        // Write raw frame bytes (PID + data + checksum)
        fwrite($this->serial, $frame->toBytes());

        // Read response (up to 11 bytes: 1 PID + 8 data + 1 checksum + 1 sync)
        $response = '';
        $deadline = microtime(true) + $this->timeout;
        while (strlen($response) < 11 && microtime(true) < $deadline) {
            $chunk = fread($this->serial, 11 - strlen($response));
            if ($chunk !== false && $chunk !== '') {
                $response .= $chunk;
            } else {
                usleep(1000);  // 1ms wait
            }
        }

        $this->latency = microtime(true) - $start;

        if ($response === '') {
            throw new \RuntimeException('No response from LIN bus');
        }

        // Skip optional sync field (0x55) at start of response
        if (strlen($response) > 0 && ord($response[0]) === 0x55) {
            $response = substr($response, 1);
        }

        return LinFrame::fromBytes($response);
    }

    public function sendAsync(FrameInterface $frame): mixed
    {
        throw new \RuntimeException('LIN does not support async operations');
    }

    public function getLatency(): float
    {
        return $this->latency;
    }

    public function supportsAsync(): bool
    {
        return false;
    }
}
