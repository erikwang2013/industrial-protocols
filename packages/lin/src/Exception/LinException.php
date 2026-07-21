<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Lin\Exception;

class LinException extends \RuntimeException
{
    public static function invalidFrameId(int $id): self
    {
        return new self(sprintf('Invalid LIN frame ID: %d (valid range: 0-63)', $id));
    }

    public static function invalidDataLength(int $length): self
    {
        return new self(sprintf('Invalid LIN data length: %d (valid range: 1-8)', $length));
    }

    public static function checksumMismatch(int $expected, int $actual): self
    {
        return new self(sprintf('LIN checksum mismatch: expected 0x%02X, got 0x%02X', $expected, $actual));
    }

    public static function parityError(int $pid): self
    {
        return new self(sprintf('LIN PID parity error in byte 0x%02X', $pid));
    }
}
