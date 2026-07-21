<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Encoding;

use Erikwang2013\IndustrialProtocols\OpcUa\Types\NodeId;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\StatusCode;

class BinaryDecoder
{
    private int $offset = 0;

    public function __construct(
        private string $buffer,
    ) {}

    public function readBoolean(): bool
    {
        $v = ord($this->buffer[$this->offset]);
        $this->offset++;
        return $v !== 0;
    }

    public function readByte(): int
    {
        $v = ord($this->buffer[$this->offset]);
        $this->offset++;
        return $v;
    }

    public function readSByte(): int
    {
        $v = unpack('c', substr($this->buffer, $this->offset, 1))[1];
        $this->offset++;
        return $v;
    }

    public function readInt16(): int
    {
        $v = unpack('v', substr($this->buffer, $this->offset, 2))[1];
        $this->offset += 2;
        // Sign-extend from 16-bit unsigned to PHP int
        if ($v >= 0x8000) {
            $v -= 0x10000;
        }
        return $v;
    }

    public function readUInt16(): int
    {
        $v = unpack('v', substr($this->buffer, $this->offset, 2))[1];
        $this->offset += 2;
        return $v;
    }

    public function readInt32(): int
    {
        $v = unpack('V', substr($this->buffer, $this->offset, 4))[1];
        $this->offset += 4;
        // Sign-extend from 32-bit unsigned to PHP int
        if ($v >= 0x80000000) {
            $v -= 0x100000000;
        }
        return $v;
    }

    public function readUInt32(): int
    {
        $v = unpack('V', substr($this->buffer, $this->offset, 4))[1];
        $this->offset += 4;
        return $v;
    }

    public function readInt64(): int
    {
        $v = unpack('P', substr($this->buffer, $this->offset, 8))[1];
        $this->offset += 8;
        return $v;
    }

    public function readUInt64(): int
    {
        $v = unpack('P', substr($this->buffer, $this->offset, 8))[1];
        $this->offset += 8;
        return $v;
    }

    public function readByteString(): string
    {
        $len = $this->readInt32();
        if ($len < 0) {
            return '';
        }
        $v = substr($this->buffer, $this->offset, $len);
        $this->offset += $len;
        return $v;
    }

    public function readFloat(): float
    {
        $v = unpack('g', substr($this->buffer, $this->offset, 4))[1];
        $this->offset += 4;
        return $v;
    }

    public function readDouble(): float
    {
        $v = unpack('e', substr($this->buffer, $this->offset, 8))[1];
        $this->offset += 8;
        return $v;
    }

    public function readString(): string
    {
        $len = $this->readInt32();
        $v = substr($this->buffer, $this->offset, $len);
        $this->offset += $len;
        return $v;
    }

    public function readGuid(): string
    {
        $v = substr($this->buffer, $this->offset, 16);
        $this->offset += 16;
        $h = bin2hex($v);
        return substr($h, 0, 8) . '-' .
            substr($h, 8, 4) . '-' .
            substr($h, 12, 4) . '-' .
            substr($h, 16, 4) . '-' .
            substr($h, 20, 12);
    }

    public function readNodeId(): NodeId
    {
        $remaining = substr($this->buffer, $this->offset);
        $id = NodeId::decode($remaining);
        $this->offset += $id->getEncodingLength();
        return $id;
    }

    public function readStatusCode(): StatusCode
    {
        $v = StatusCode::decode(substr($this->buffer, $this->offset, 4));
        $this->offset += 4;
        return $v;
    }

    public function remaining(): int
    {
        return strlen($this->buffer) - $this->offset;
    }

    public function getPosition(): int
    {
        return $this->offset;
    }
}
