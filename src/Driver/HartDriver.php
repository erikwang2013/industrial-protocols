<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Hart\Driver;

use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;
use Erikwang2013\IndustrialProtocols\Hart\Exception\HartException;
use Erikwang2013\IndustrialProtocols\Hart\Frame\HartFrame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * HART modem driver (serial/USB via HART modem).
 *
 * HART uses Bell 202 FSK modulation at 1200 baud on a 4-20mA current loop.
 * Communication happens through a HART modem (USB or serial).
 */
class HartDriver implements DriverInterface
{
    private $handle = null;
    private float $lastLatency = 0.0;

    public function __construct(
        private string $device,    // e.g. /dev/ttyUSB0 or COM3
        private int $baudRate = 1200,
        private float $timeout = 5.0,
        private int $preambleCount = 5,
    ) {}

    public function connect(): void
    {
        $this->handle = @fopen($this->device, 'r+b');
        if (!$this->handle) {
            throw new \RuntimeException("Failed to open HART modem on: {$this->device}");
        }
        stream_set_blocking($this->handle, true);
        stream_set_timeout($this->handle, (int) $this->timeout);

        $cmd = sprintf(
            'stty -F %s %d cs8 -cstopb -parenb 2>/dev/null',
            escapeshellarg($this->device),
            $this->baudRate
        );
        @exec($cmd);
    }

    public function disconnect(): void
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->handle !== null;
    }

    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$this->handle) {
            throw new HartException('HART driver not connected');
        }

        $start = microtime(true);
        $bytes = $frame->toBytes();

        // HART modem needs a carrier detect delay before sending
        usleep(5000); // 5 ms RTS/CTS delay for HART modem
        fwrite($this->handle, $bytes);

        // Wait for response (HART at 1200 baud — 1 byte ~= 8 ms)
        $maxWait = (int) ($this->timeout * 1000000);
        $waited = 0;
        $response = '';
        while ($waited < $maxWait) {
            $chunk = fread($this->handle, 256);
            if ($chunk !== false && $chunk !== '') {
                $response .= $chunk;
                // When we have enough bytes and see a preamble, try to parse
                if (strlen($response) >= 9) {
                    break;
                }
            }
            usleep(10000); // 10 ms
            $waited += 10000;
        }

        $this->lastLatency = (microtime(true) - $start) * 1000;

        if ($response === '' || strlen($response) < 9) {
            throw new ConnectionTimeoutException('HART modem read timeout');
        }

        return HartFrame::parse($response);
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
