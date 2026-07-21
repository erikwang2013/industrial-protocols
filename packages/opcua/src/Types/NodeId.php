<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Types;

class NodeId
{
    public function __construct(
        public readonly int $namespace = 0,
        public readonly int|string $identifier = 0,
    ) {}

    public function encode(): string
    {
        if (is_int($this->identifier)) {
            if ($this->namespace === 0 && $this->identifier <= 255) {
                // TwoByte
                return chr(0x00) . chr($this->identifier);
            }
            if ($this->namespace <= 255 && $this->identifier <= 65535) {
                // FourByte
                return chr(0x01) . chr($this->namespace) . pack('v', $this->identifier);
            }
            // Numeric
            return chr(0x02) . pack('v', $this->namespace) . pack('V', $this->identifier);
        }
        // String
        $str = (string) $this->identifier;
        return chr(0x03) . pack('v', $this->namespace) . pack('V', strlen($str)) . $str;
    }

    public static function decode(string $bytes): self
    {
        $form = ord($bytes[0]);

        return match ($form) {
            0x00 => new self(0, ord($bytes[1])),
            0x01 => new self(ord($bytes[1]), unpack('v', substr($bytes, 2, 2))[1]),
            0x02 => new self(
                unpack('v', substr($bytes, 1, 2))[1],
                unpack('V', substr($bytes, 3, 4))[1]
            ),
            0x03 => (function (string $raw): self {
                $ns  = unpack('v', substr($raw, 1, 2))[1];
                $len = unpack('V', substr($raw, 3, 4))[1];
                return new self($ns, substr($raw, 7, $len));
            })($bytes),
        };
    }

    public function getEncodingLength(): int
    {
        if (is_int($this->identifier)) {
            if ($this->namespace === 0 && $this->identifier <= 255) {
                return 2;  // 1 byte form + 1 byte id
            }
            if ($this->namespace <= 255 && $this->identifier <= 65535) {
                return 4;  // 1 byte form + 1 byte ns + 2 byte id
            }
            return 7;  // 1 byte form + 2 byte ns + 4 byte id
        }
        return 7 + strlen($this->identifier);  // 1 byte form + 2 byte ns + 4 byte len + string
    }

    public function toString(): string
    {
        $ns = $this->namespace > 0 ? "ns={$this->namespace};" : '';
        if (is_int($this->identifier)) {
            return "{$ns}i={$this->identifier}";
        }
        return "{$ns}s={$this->identifier}";
    }
}
