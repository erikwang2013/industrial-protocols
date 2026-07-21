<?php

namespace Erikwang2013\IndustrialProtocols\Modbus\Driver;

use Erikwang2013\IndustrialProtocols\Exception\CrcException;
use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;
use Erikwang2013\IndustrialProtocols\Modbus\Exception\ModbusException;
use Erikwang2013\IndustrialProtocols\Modbus\Frame\ModbusFrame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class ModbusRtuDriver implements DriverInterface
{
    private $handle = null;
    private float $lastLatency = 0.0;

    public function __construct(
        private string $device,   // e.g. /dev/ttyUSB0 or COM3
        private int $baudRate = 19200,
        private string $parity = 'N',
        private int $dataBits = 8,
        private int $stopBits = 1,
        private float $timeout = 3.0,
    ) {}

    public function connect(): void
    {
        $this->handle = @fopen($this->device, 'r+b');
        if (!$this->handle) {
            throw new \RuntimeException("Failed to open serial port: {$this->device}");
        }
        stream_set_blocking($this->handle, true);
        stream_set_timeout($this->handle, (int) $this->timeout);

        // Set serial params via stty (Linux) or mode (Windows)
        $cmd = sprintf(
            'stty -F %s %d cs%d -cstopb %s -parenb 2>/dev/null',
            escapeshellarg($this->device),
            $this->baudRate,
            $this->dataBits,
            $this->parity === 'N' ? '' : 'parenb'
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
            throw new ModbusException('Not connected');
        }

        $start = microtime(true);
        $bytes = $frame->toBytes();

        // Add CRC16 for RTU mode and send
        $frameWithCrc = ModbusFrame::appendCrc($bytes);
        fwrite($this->handle, $frameWithCrc);

        // Read response
        $response = fread($this->handle, 256);
        $this->lastLatency = (microtime(true) - $start) * 1000;

        if ($response === false || strlen($response) < 4) {
            if ($this->handle && stream_get_meta_data($this->handle)['timed_out']) {
                throw new ConnectionTimeoutException('RTU read timeout');
            }
            throw new ModbusException('RTU read failed: response too short');
        }

        // Validate CRC
        if (!ModbusFrame::validateCrc($response)) {
            throw new CrcException('RTU CRC mismatch');
        }

        // Return response WITHOUT CRC bytes (last 2 bytes)
        $frameClass = get_class($frame);
        return $frameClass::fromBytes(substr($response, 0, -2));
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
