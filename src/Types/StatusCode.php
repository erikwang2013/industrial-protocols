<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Types;

class StatusCode
{
    public const GOOD                 = 0x00000000;
    public const BAD_UNEXPECTED_ERROR = 0x80010000;
    public const BAD_TIMEOUT          = 0x800A0000;
    public const BAD_NODE_ID_UNKNOWN  = 0x80340000;

    public function __construct(
        public readonly int $code = 0,
    ) {}

    public function isGood(): bool
    {
        return ($this->code & 0x80000000) === 0;
    }

    public function isBad(): bool
    {
        return ($this->code & 0xC0000000) === 0x80000000;
    }

    public function encode(): string
    {
        return pack('V', $this->code);
    }

    public static function decode(string $bytes): self
    {
        return new self(unpack('V', substr($bytes, 0, 4))[1]);
    }
}
