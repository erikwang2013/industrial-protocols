<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\CcLink\Frame;

use Erikwang2013\IndustrialProtocols\CcLink\Exception\CcLinkException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * CC-Link frame (RS-485 master-slave token passing).
 *
 * Frame format: StationNo(1) + Flags(1) + DataLen(1) + Data(0-255) + CRC(2)
 * Flags bits: | 7:Dir | 6:5-Reserved | 4:3-Type | 2:1:0-Reserved |
 *   Dir: 0=Master->Slave, 1=Slave->Master
 *   Type: 00=Cyclic, 01=Transient
 */
class CcLinkFrame implements FrameInterface
{
    public const DIR_MASTER_TO_SLAVE = 0x00;
    public const DIR_SLAVE_TO_MASTER = 0x80;

    public const TYPE_CYCLIC = 0x00;
    public const TYPE_TRANSIENT = 0x10;

    private function __construct(
        private int $stationNo,
        private int $flags,
        private string $data,
    ) {}

    /**
     * Create a cyclic transmission frame (master -> slave).
     */
    public static function cyclic(int $stationNo, string $data): self
    {
        return new self($stationNo, self::DIR_MASTER_TO_SLAVE | self::TYPE_CYCLIC, $data);
    }

    /**
     * Create a transient transmission frame.
     */
    public static function transient(int $stationNo, string $data): self
    {
        return new self($stationNo, self::DIR_MASTER_TO_SLAVE | self::TYPE_TRANSIENT, $data);
    }

    /**
     * Create a cyclic response frame (slave -> master).
     */
    public static function response(int $stationNo, string $data): self
    {
        return new self($stationNo, self::DIR_SLAVE_TO_MASTER | self::TYPE_CYCLIC, $data);
    }

    /**
     * Calculate CC-Link CRC (CRC-16/XMODEM).
     */
    public static function crc16(string $data): int
    {
        $crc = 0x0000;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }
        return $crc;
    }

    public function toBytes(): string
    {
        $dataLen = chr(strlen($this->data));
        $frame = chr($this->stationNo) . chr($this->flags) . $dataLen . $this->data;
        $crc = self::crc16($frame);
        return $frame . chr(($crc >> 8) & 0xFF) . chr($crc & 0xFF);
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 5) {
            throw new CcLinkException('CC-Link frame too short: ' . strlen($bytes) . ' bytes');
        }

        $dataLen = ord($bytes[2]);
        $expectedLen = 3 + $dataLen + 2; // header + data + CRC
        if (strlen($bytes) < $expectedLen) {
            throw new CcLinkException('CC-Link frame incomplete');
        }

        // Validate CRC
        $frameData = substr($bytes, 0, $expectedLen - 2);
        $expectedCrc = self::crc16($frameData);
        $actualCrc = (ord($bytes[$expectedLen - 2]) << 8) | ord($bytes[$expectedLen - 1]);
        if ($expectedCrc !== $actualCrc) {
            throw new CcLinkException('CC-Link CRC mismatch');
        }

        return new self(
            stationNo: ord($bytes[0]),
            flags: ord($bytes[1]),
            data: substr($bytes, 3, $dataLen),
        );
    }

    public function getData(): array
    {
        return [
            'station_no' => $this->stationNo,
            'flags' => $this->flags,
            'direction' => ($this->flags & 0x80) ? 'slave_to_master' : 'master_to_slave',
            'type' => ($this->flags & 0x10) ? 'transient' : 'cyclic',
            'data_len' => strlen($this->data),
        ];
    }

    public function getStationNo(): int { return $this->stationNo; }
    public function getFlags(): int { return $this->flags; }
    public function getRawData(): string { return $this->data; }
}
