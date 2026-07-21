<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Modbus\Frame;

abstract class ModbusFrame
{
    /**
     * Calculate Modbus RTU CRC16.
     */
    public static function crc16(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x0001) {
                    $crc = ($crc >> 1) ^ 0xA001;
                } else {
                    $crc >>= 1;
                }
            }
        }
        // Byte-swap to standard CRC-16/Modbus representation
        return (($crc & 0xFF) << 8) | (($crc >> 8) & 0xFF);
    }

    public static function appendCrc(string $data): string
    {
        $crc = self::crc16($data);
        return $data . chr($crc & 0xFF) . chr($crc >> 8);
    }

    public static function validateCrc(string $frame): bool
    {
        $len = strlen($frame);
        if ($len < 2) return false;
        $data = substr($frame, 0, $len - 2);
        $expected = self::crc16($data);
        $actual = ord($frame[$len - 1]) << 8 | ord($frame[$len - 2]);
        return $expected === $actual;
    }
}
