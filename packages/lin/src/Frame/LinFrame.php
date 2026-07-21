<?php

namespace Erikwang2013\IndustrialProtocols\Lin\Frame;

use Erikwang2013\IndustrialProtocols\Lin\Exception\LinException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * LIN bus frame (LIN 2.x specification).
 *
 * Frame structure:
 *   Sync Break (13+ bit-times low)
 *   Sync Field (0x55)
 *   Protected ID (6-bit ID + 2 parity bits P0,P1)
 *   Data (1-8 bytes)
 *   Checksum (classic: data only; enhanced: PID + data)
 */
class LinFrame implements FrameInterface
{
    public const CLASSIC_CHECKSUM = 'classic';
    public const ENHANCED_CHECKSUM = 'enhanced';

    /** IDs 0-59: signal carrying; 60-61: diagnostic; 62-63: reserved */
    public const MAX_ID = 63;

    private int $id;
    private array $data;
    private string $checksumType;
    private int $pid;

    /**
     * @param int $id Frame ID (0-63)
     * @param array $data Array of byte values (0-255 each)
     * @param string $checksumType 'classic' or 'enhanced'
     */
    public function __construct(int $id, array $data = [], string $checksumType = self::CLASSIC_CHECKSUM)
    {
        if ($id < 0 || $id > self::MAX_ID) {
            throw LinException::invalidFrameId($id);
        }
        if (count($data) > 8) {
            throw LinException::invalidDataLength(count($data));
        }

        $this->id = $id;
        $this->data = $data;
        $this->checksumType = $checksumType;
        $this->pid = self::computePid($id);
    }

    // ---- PID encoding ----

    /**
     * Compute Protected ID from 6-bit frame ID.
     * P0 = ID0 ^ ID1 ^ ID2 ^ ID4  (even parity over bits 0,1,2,4)
     * P1 = !(ID1 ^ ID3 ^ ID4 ^ ID5) (odd parity over bits 1,3,4,5)
     */
    public static function computePid(int $id): int
    {
        $p0 = self::parityBits($id, [0, 1, 2, 4]);
        $p1 = 1 - self::parityBits($id, [1, 3, 4, 5]);
        return ($id & 0x3F) | ($p0 << 6) | ($p1 << 7);
    }

    /**
     * Verify parity bits in a PID byte.
     * Returns true if P0 and P1 are correct for the embedded frame ID.
     */
    public static function verifyPid(int $pid): bool
    {
        $id = $pid & 0x3F;
        $p0 = ($pid >> 6) & 1;
        $p1 = ($pid >> 7) & 1;
        return $p0 === self::parityBits($id, [0, 1, 2, 4])
            && $p1 === (1 - self::parityBits($id, [1, 3, 4, 5]));
    }

    /**
     * Extract 6-bit frame ID from a PID byte, with optional parity check.
     */
    public static function pidToId(int $pid): int
    {
        if (!self::verifyPid($pid)) {
            throw LinException::parityError($pid);
        }
        return $pid & 0x3F;
    }

    // ---- Checksum ----

    /**
     * Classic checksum: sum of data bytes only (LIN 1.x).
     */
    public static function classicChecksum(array $data): int
    {
        $sum = 0;
        foreach ($data as $byte) {
            $sum += ($byte & 0xFF);
            if ($sum >= 256) {
                $sum = ($sum - 255);  // carry-safe addition per LIN spec
            }
        }
        return (0xFF - $sum) & 0xFF;
    }

    /**
     * Enhanced checksum: sum of PID + data bytes (LIN 2.x).
     */
    public static function enhancedChecksum(int $pid, array $data): int
    {
        return self::classicChecksum(array_merge([$pid], $data));
    }

    /**
     * Compute the checksum for this frame.
     */
    public function computeChecksum(): int
    {
        if ($this->checksumType === self::CLASSIC_CHECKSUM) {
            return self::classicChecksum($this->data);
        }
        return self::enhancedChecksum($this->pid, $this->data);
    }

    // ---- Sync ----

    /**
     * Sync field is always 0x55 (alternating bits for baud rate sync).
     */
    public static function syncField(): int
    {
        return 0x55;
    }

    // ---- FrameInterface ----

    public function toBytes(): string
    {
        $bytes = chr($this->pid);
        foreach ($this->data as $b) {
            $bytes .= chr($b & 0xFF);
        }
        $bytes .= chr($this->computeChecksum());
        return $bytes;
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 2) {
            throw new LinException('LIN frame too short');
        }

        $pid = ord($bytes[0]);
        $id = self::pidToId($pid);

        $dataLen = strlen($bytes) - 2;  // minus PID and checksum
        $data = [];
        for ($i = 1; $i <= $dataLen; $i++) {
            $data[] = ord($bytes[$i]);
        }

        $receivedCS = ord($bytes[strlen($bytes) - 1]);

        // Try enhanced checksum first (LIN 2.x), then classic (LIN 1.x)
        $enhCS = self::enhancedChecksum($pid, $data);
        $clCS = self::classicChecksum($data);
        if ($receivedCS !== $enhCS && $receivedCS !== $clCS) {
            throw LinException::checksumMismatch(
                ($receivedCS === $enhCS || $receivedCS !== $clCS) ? $enhCS : $clCS,
                $receivedCS
            );
        }

        $csType = ($receivedCS === $enhCS) ? self::ENHANCED_CHECKSUM : self::CLASSIC_CHECKSUM;
        $frame = new self($id, $data, $csType);
        return $frame;
    }

    public function getData(): array
    {
        return [
            'id' => $this->id,
            'pid' => $this->pid,
            'data' => $this->data,
            'checksum_type' => $this->checksumType,
            'checksum' => $this->computeChecksum(),
        ];
    }

    // ---- Accessors ----

    public function getId(): int { return $this->id; }
    public function getPid(): int { return $this->pid; }
    public function getRawData(): array { return $this->data; }
    public function getChecksumType(): string { return $this->checksumType; }

    // ---- Helpers ----

    /**
     * Compute even parity over selected bit positions.
     */
    private static function parityBits(int $value, array $bits): int
    {
        $p = 0;
        foreach ($bits as $b) {
            $p ^= ($value >> $b) & 1;
        }
        return $p;
    }
}
