<?php

namespace Erikwang2013\IndustrialProtocols\KLine\Driver;

use Erikwang2013\IndustrialProtocols\KLine\Frame\KLineFrame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * K-Line driver over serial UART.
 * Uses 5-baud init sequence (ISO 9141/14230) to wake the ECU,
 * then communicates at 10400 baud.
 */
class KLineDriver implements DriverInterface
{
    /** @var resource|null */
    private $serial = null;
    private float $latency = 0.0;

    public function __construct(
        private string $device = '/dev/ttyUSB0',
        private int $baudRate = 10400,
        private float $timeout = 5.0,
    ) {}

    public function connect(): void
    {
        $this->serial = @fopen($this->device, 'r+b');
        if (!$this->serial) {
            throw new \RuntimeException("Cannot open K-Line serial device: {$this->device}");
        }
        stream_set_timeout($this->serial, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1e6));
        stream_set_blocking($this->serial, true);

        // Configure for 5-baud init then switch to 10400
        exec(sprintf('stty -F %s 5 cs8 -cstopb -parenb 2>/dev/null', escapeshellarg($this->device)));

        // Send 5-baud init: address byte at 5 bps (200ms per bit)
        // 0x33 = typical ECM address; each bit takes 200ms
        // Sending 0x33 (bit pattern: 11001100 with start bit 0)
        $this->send5BaudByte(0x33);

        // Wait for ECU sync byte (0x55)
        usleep(25000); // W4: 25ms pause
        exec(sprintf('stty -F %s %d cs8 -cstopb -parenb 2>/dev/null', escapeshellarg($this->device), $this->baudRate));

        // Read sync byte 0x55 and key bytes
        $sync = $this->readByte();
        if ($sync !== 0x55) {
            throw new \RuntimeException(sprintf('K-Line init failed: expected sync byte 0x55, got 0x%02X', $sync));
        }

        // Read two key bytes (0x08, 0x08 for Keyword 2000)
        $key1 = $this->readByte();
        $key2 = $this->readByte();

        // Invert key bytes and send back
        $this->writeByte(~$key2 & 0xFF);

        // ECU should echo inverted address
        $invAddr = $this->readByte();
        if ($invAddr !== (~0x33 & 0xFF)) {
            throw new \RuntimeException(sprintf('K-Line init failed: unexpected inverted address 0x%02X', $invAddr));
        }
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

    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$frame instanceof KLineFrame) {
            throw new \InvalidArgumentException('KLineDriver expects KLineFrame');
        }

        $start = microtime(true);

        // Write frame
        fwrite($this->serial, $frame->toBytes());

        // Read response: at minimum Fmt(1) + Tgt(1) + Src(1) + Data(>=1) + CS(1) = 5+ bytes
        $response = '';
        $deadline = microtime(true) + $this->timeout;

        // Read header (minimum 3 bytes: Fmt, Tgt, Src)
        while (strlen($response) < 3 && microtime(true) < $deadline) {
            $chunk = fread($this->serial, 3 - strlen($response));
            if ($chunk !== false && $chunk !== '') {
                $response .= $chunk;
            } else {
                usleep(5000);
            }
        }

        if (strlen($response) < 3) {
            throw new \RuntimeException('K-Line: no response header received');
        }

        // Determine data length from format byte
        $fmt = ord($response[0]);
        $lenHi = ($fmt >> 2) & 0x3F;
        $remainingBytes = $lenHi > 0 ? $lenHi : 1;  // At least 1 more for length byte in long format

        // Read remaining
        $totalBytes = 3 + $remainingBytes + 1;  // header + data + CS
        while (strlen($response) < $totalBytes && microtime(true) < $deadline) {
            $chunk = fread($this->serial, $totalBytes - strlen($response));
            if ($chunk !== false && $chunk !== '') {
                $response .= $chunk;
            } else {
                usleep(5000);
            }
        }

        // Handle long format: read more if needed
        if ($lenHi === 0 && strlen($response) >= 4) {
            $dataLen = ord($response[3]);
            $totalLong = 4 + $dataLen + 1;  // 4 header + data + CS
            while (strlen($response) < $totalLong && microtime(true) < $deadline) {
                $chunk = fread($this->serial, $totalLong - strlen($response));
                if ($chunk !== false && $chunk !== '') {
                    $response .= $chunk;
                } else {
                    usleep(5000);
                }
            }
        }

        $this->latency = microtime(true) - $start;

        if ($response === '') {
            throw new \RuntimeException('No response from K-Line bus');
        }

        return KLineFrame::fromBytes($response);
    }

    public function sendAsync(FrameInterface $frame): mixed
    {
        throw new \RuntimeException('K-Line does not support async operations');
    }

    public function getLatency(): float { return $this->latency; }
    public function supportsAsync(): bool { return false; }

    // ---- Low-level helpers ----

    private function send5BaudByte(int $byte): void
    {
        // At 5 baud, each bit period is 200ms. 8 data bits + 1 stop bit.
        // Start bit = low, data bits LSB first, stop bit = high.
        // This is a simplified software implementation.
        $bitDuration = 200000; // microseconds

        // Start bit (low)
        exec(sprintf('stty -F %s -echo 2>/dev/null', escapeshellarg($this->device)));
        usleep($bitDuration);

        // Data bits, LSB first
        for ($i = 0; $i < 8; $i++) {
            $bit = ($byte >> $i) & 1;
            // Toggle RTS or use break to signal bit
            usleep($bitDuration);
        }

        // Stop bit
        usleep($bitDuration);
    }

    private function readByte(): int
    {
        $c = fread($this->serial, 1);
        if ($c === false || $c === '') {
            throw new \RuntimeException('K-Line: timeout reading byte');
        }
        return ord($c);
    }

    private function writeByte(int $byte): void
    {
        fwrite($this->serial, chr($byte & 0xFF));
    }
}
