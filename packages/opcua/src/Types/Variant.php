<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Types;

use RuntimeException;

class Variant
{
    // Type identifiers for encoding
    public const TYPE_NULL    = 0;
    public const TYPE_BOOLEAN = 1;
    public const TYPE_SBYTE   = 2;
    public const TYPE_BYTE    = 3;
    public const TYPE_INT16   = 4;
    public const TYPE_UINT16  = 5;
    public const TYPE_INT32   = 6;
    public const TYPE_UINT32  = 7;
    public const TYPE_INT64   = 8;
    public const TYPE_UINT64  = 9;
    public const TYPE_FLOAT   = 10;
    public const TYPE_DOUBLE  = 11;
    public const TYPE_STRING  = 12;
    public const TYPE_NODE_ID = 15;  // 0x0F

    public function __construct(
        private mixed $value = null,
        private int $type = self::TYPE_NULL,
        private bool $isArray = false,
    ) {}

    public static function int32(int $v): self
    {
        return new self($v, self::TYPE_INT32);
    }

    public static function uint32(int $v): self
    {
        return new self($v, self::TYPE_UINT32);
    }

    public static function float(float $v): self
    {
        return new self($v, self::TYPE_FLOAT);
    }

    public static function double(float $v): self
    {
        return new self($v, self::TYPE_DOUBLE);
    }

    public static function bool(bool $v): self
    {
        return new self($v, self::TYPE_BOOLEAN);
    }

    public static function string(string $v): self
    {
        return new self($v, self::TYPE_STRING);
    }

    public static function nodeId(NodeId $v): self
    {
        return new self($v, self::TYPE_NODE_ID);
    }

    public static function null(): self
    {
        return new self(null, self::TYPE_NULL);
    }

    public function encode(): string
    {
        // Encoding mask: bit 0-5 = type, bit 7 = array flag
        $mask = $this->type;
        if ($this->isArray) {
            $mask |= 0x80;
        }

        $body = match ($this->type) {
            self::TYPE_NULL    => '',
            self::TYPE_BOOLEAN => chr($this->value ? 1 : 0),
            self::TYPE_SBYTE   => pack('c', $this->value),
            self::TYPE_BYTE    => chr($this->value),
            self::TYPE_INT16   => pack('v', $this->value),
            self::TYPE_UINT16  => pack('v', $this->value),
            self::TYPE_INT32   => pack('V', $this->value),
            self::TYPE_UINT32  => pack('V', $this->value),
            self::TYPE_INT64   => pack('P', $this->value),
            self::TYPE_UINT64  => pack('P', $this->value),
            self::TYPE_FLOAT   => pack('g', $this->value),
            self::TYPE_DOUBLE  => pack('e', $this->value),
            self::TYPE_STRING  => pack('V', strlen($this->value)) . $this->value,
            self::TYPE_NODE_ID => $this->value->encode(),
            default => throw new RuntimeException("Unsupported variant type: {$this->type}"),
        };

        if ($this->isArray) {
            $count = count($this->value);
            return chr($mask) . pack('V', $count) . $body;
        }

        return chr($mask) . $body;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getType(): int
    {
        return $this->type;
    }
}
