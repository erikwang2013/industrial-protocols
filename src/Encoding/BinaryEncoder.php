<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Encoding;

use Erikwang2013\IndustrialProtocols\OpcUa\Types\NodeId;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\StatusCode;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\Variant;

class BinaryEncoder
{
    private string $buffer = '';

    public function writeBoolean(bool $v): self
    {
        $this->buffer .= chr($v ? 1 : 0);
        return $this;
    }

    public function writeSByte(int $v): self
    {
        $this->buffer .= pack('c', $v);
        return $this;
    }

    public function writeByte(int $v): self
    {
        $this->buffer .= chr($v);
        return $this;
    }

    public function writeInt16(int $v): self
    {
        $this->buffer .= pack('v', $v);
        return $this;
    }

    public function writeUInt16(int $v): self
    {
        $this->buffer .= pack('v', $v);
        return $this;
    }

    public function writeInt32(int $v): self
    {
        $this->buffer .= pack('V', $v);
        return $this;
    }

    public function writeUInt32(int $v): self
    {
        $this->buffer .= pack('V', $v);
        return $this;
    }

    public function writeInt64(int $v): self
    {
        $this->buffer .= pack('P', $v);
        return $this;
    }

    public function writeUInt64(int $v): self
    {
        $this->buffer .= pack('P', $v);
        return $this;
    }

    public function writeFloat(float $v): self
    {
        $this->buffer .= pack('g', $v);
        return $this;
    }

    public function writeDouble(float $v): self
    {
        $this->buffer .= pack('e', $v);
        return $this;
    }

    public function writeString(string $v): self
    {
        $this->buffer .= pack('V', strlen($v)) . $v;
        return $this;
    }

    public function writeByteString(string $v): self
    {
        $this->buffer .= pack('V', strlen($v)) . $v;
        return $this;
    }

    public function writeGuid(string $v): self
    {
        $this->buffer .= hex2bin(str_replace('-', '', $v));
        return $this;
    }

    public function writeNodeId(NodeId $id): self
    {
        $this->buffer .= $id->encode();
        return $this;
    }

    public function writeStatusCode(StatusCode $sc): self
    {
        $this->buffer .= $sc->encode();
        return $this;
    }

    public function writeVariant(Variant $v): self
    {
        $this->buffer .= $v->encode();
        return $this;
    }

    public function writeBytes(string $bytes): self
    {
        $this->buffer .= $bytes;
        return $this;
    }

    public function toBytes(): string
    {
        return $this->buffer;
    }
}
