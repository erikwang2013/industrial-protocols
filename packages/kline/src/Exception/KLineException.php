<?php

namespace Erikwang2013\IndustrialProtocols\KLine\Exception;

class KLineException extends \RuntimeException
{
    public static function checksumMismatch(int $expected, int $actual): self
    {
        return new self(sprintf('K-Line checksum mismatch: expected 0x%02X, got 0x%02X', $expected, $actual));
    }

    public static function invalidFrameFormat(string $reason): self
    {
        return new self('Invalid K-Line frame format: ' . $reason);
    }
}
