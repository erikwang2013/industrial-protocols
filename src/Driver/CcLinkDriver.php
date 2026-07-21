<?php

namespace Erikwang2013\IndustrialProtocols\CcLink\Driver;

use Erikwang2013\IndustrialProtocols\CcLink\Exception\CcLinkException;
use Erikwang2013\IndustrialProtocols\CcLink\Frame\CcLinkFrame;
use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * CC-Link RS-485 serial driver.
 *
 * CC-Link uses RS-485 at speeds from 156 kbps to 10 Mbps
 * with master-slave token passing.
 */
class CcLinkDriver implements DriverInterface
{
    private $handle = null;
    private float $lastLatency = 0.0;

    public function __construct(
        private string $device,    // e.g. /dev/ttyUSB0 or COM3
        private int $baudRate = 156000,
        private float $timeout = 3.0,
    ) {}

    public function connect(): void
    {
        $this->handle = @fopen($this->device, 'r+b');
        if (!$this->handle) {
            throw new \RuntimeException("Failed to open CC-Link serial port: {$this->device}");
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
            throw new CcLinkException('CC-Link driver not connected');
        }

        $start = microtime(true);
        $bytes = $frame->toBytes();

        // RS-485: assert driver enable then send
        fwrite($this->handle, $bytes);

        // Wait for response (cyclic — expect immediate response)
        $response = fread($this->handle, 256);
        $this->lastLatency = (microtime(true) - $start) * 1000;

        if ($response === false || strlen($response) < 5) {
            if ($this->handle && stream_get_meta_data($this->handle)['timed_out']) {
                throw new ConnectionTimeoutException('CC-Link serial read timeout');
            }
            throw new CcLinkException('CC-Link serial read failed');
        }

        return CcLinkFrame::fromBytes($response);
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
