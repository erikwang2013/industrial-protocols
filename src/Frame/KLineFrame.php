<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\KLine\Frame;

use Erikwang2013\IndustrialProtocols\KLine\Exception\KLineException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * ISO 9141 / ISO 14230 (KWP2000) message frame.
 *
 * Message format:
 *   Fmt  (1 byte) - format byte: length in upper 6 bits, addressing mode in lower 2
 *   Tgt  (1 byte) - target address
 *   Src  (1 byte) - source address
 *   Len  (optional, 1 byte) - additional length byte for messages > 63 bytes
 *   Data (0-255 bytes)
 *   CS   (1 byte) - checksum (simple sum of all preceding bytes, modulo 256)
 */
class KLineFrame implements FrameInterface
{
    /** Addressing modes */
    public const ADDR_CARB = 0x00;       // CARB (no address)
    public const ADDR_PHYSICAL = 0x01;   // Physical addressing
    public const ADDR_FUNCTIONAL = 0x02; // Functional addressing

    private int $target;
    private int $source;
    private array $data;
    private int $addrMode;

    /**
     * @param int $target Target address (0x33 for ECM typical)
     * @param int $source Source/tester address (0xF1 for scan tool typical)
     * @param array $data Payload bytes (service ID + parameters)
     * @param int $addrMode Addressing mode
     */
    public function __construct(
        int $target = 0x33,
        int $source = 0xF1,
        array $data = [],
        int $addrMode = self::ADDR_PHYSICAL,
    ) {
        $this->target = $target & 0xFF;
        $this->source = $source & 0xFF;
        $this->data = $data;
        $this->addrMode = $addrMode;
    }

    /**
     * Compute K-Line checksum: simple sum of all bytes before CS, modulo 256.
     */
    public function computeChecksum(): int
    {
        $header = $this->buildHeader();
        $sum = 0;
        foreach (str_split($header) as $c) {
            $sum += ord($c);
        }
        foreach ($this->data as $b) {
            $sum += ($b & 0xFF);
        }
        return $sum & 0xFF;
    }

    /**
     * Build header bytes as a string.
     */
    private function buildHeader(): string
    {
        $dataLen = count($this->data);

        if ($dataLen <= 63) {
            // Short format: Fmt = length in upper 6 bits | addrMode
            $fmt = (($dataLen & 0x3F) << 2) | ($this->addrMode & 0x03);
            $header = chr($fmt) . chr($this->target & 0xFF) . chr($this->source & 0xFF);
        } else {
            // Long format: Fmt upper 6 bits = 0, Len byte follows
            $fmt = ($this->addrMode & 0x03);
            $header = chr($fmt) . chr($this->target & 0xFF) . chr($this->source & 0xFF) . chr($dataLen & 0xFF);
        }

        return $header;
    }

    // ---- FrameInterface ----

    public function toBytes(): string
    {
        $header = $this->buildHeader();
        $payload = '';
        foreach ($this->data as $b) {
            $payload .= chr($b & 0xFF);
        }
        $cs = $this->computeChecksum();

        return $header . $payload . chr($cs);
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 4) {
            throw KLineException::invalidFrameFormat('Frame too short (minimum 4 bytes)');
        }

        $fmt = ord($bytes[0]);
        $addrMode = $fmt & 0x03;
        $lenHi = ($fmt >> 2) & 0x3F;

        $pos = 1;
        $target = ord($bytes[$pos++]);
        $source = ord($bytes[$pos++]);

        if ($lenHi > 0) {
            $dataLen = $lenHi;
        } else {
            // Long format
            if (strlen($bytes) <= $pos) {
                throw KLineException::invalidFrameFormat('Long format missing length byte');
            }
            $dataLen = ord($bytes[$pos++]);
        }

        $totalExpected = $pos + $dataLen + 1;  // header + data + checksum
        if (strlen($bytes) < $totalExpected) {
            throw KLineException::invalidFrameFormat(
                sprintf('Expected %d bytes, got %d', $totalExpected, strlen($bytes))
            );
        }

        $data = [];
        for ($i = $pos; $i < $pos + $dataLen; $i++) {
            $data[] = ord($bytes[$i]);
        }

        $receivedCS = ord($bytes[$pos + $dataLen]);

        $frame = new self($target, $source, $data, $addrMode);
        $expectedCS = $frame->computeChecksum();

        if ($receivedCS !== $expectedCS) {
            throw KLineException::checksumMismatch($expectedCS, $receivedCS);
        }

        return $frame;
    }

    public function getData(): array
    {
        return [
            'target' => $this->target,
            'source' => $this->source,
            'addr_mode' => $this->addrMode,
            'data' => $this->data,
            'checksum' => $this->computeChecksum(),
        ];
    }

    // ---- Accessors ----

    public function getTarget(): int { return $this->target; }
    public function getSource(): int { return $this->source; }
    public function getRawData(): array { return $this->data; }
    public function getAddrMode(): int { return $this->addrMode; }
    public function getServiceId(): int { return $this->data[0] ?? 0; }
}
