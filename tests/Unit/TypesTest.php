<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Tests\Unit;

use Erikwang2013\IndustrialProtocols\OpcUa\Encoding\BinaryDecoder;
use Erikwang2013\IndustrialProtocols\OpcUa\Encoding\BinaryEncoder;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\NodeId;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\StatusCode;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\Variant;
use PHPUnit\Framework\TestCase;

class TypesTest extends TestCase
{
    public function testNodeIdTwoByte(): void
    {
        $id  = new NodeId(0, 225); // Server node
        $enc = $id->encode();
        $this->assertSame(2, strlen($enc));
        $this->assertSame(0x00, ord($enc[0])); // TwoByte form
        $this->assertSame(225, ord($enc[1]));
    }

    public function testNodeIdNumeric(): void
    {
        // identifier > 65535 forces Numeric form (doesn't fit FourByte)
        $id  = new NodeId(2, 70000);
        $enc = $id->encode();
        $this->assertSame(7, strlen($enc));
        $this->assertSame(0x02, ord($enc[0])); // Numeric form
    }

    public function testNodeIdString(): void
    {
        $id  = new NodeId(1, "MyVariable");
        $enc = $id->encode();
        $this->assertSame(0x03, ord($enc[0])); // String form
    }

    public function testNodeIdDecodeTwoByte(): void
    {
        $id  = NodeId::decode(chr(0x00) . chr(225));
        $this->assertSame(0, $id->namespace);
        $this->assertSame(225, $id->identifier);
        $this->assertSame(2, $id->getEncodingLength());
    }

    public function testNodeIdDecodeNumeric(): void
    {
        // namespace > 255 forces Numeric form, not FourByte
        $raw = chr(0x02) . pack('v', 300) . pack('V', 70000);
        $id  = NodeId::decode($raw);
        $this->assertSame(300, $id->namespace);
        $this->assertSame(70000, $id->identifier);
        $this->assertSame(7, $id->getEncodingLength());
    }

    public function testNodeIdDecodeString(): void
    {
        $str = "MyVariable";
        $raw = chr(0x03) . pack('v', 1) . pack('V', strlen($str)) . $str;
        $id  = NodeId::decode($raw);
        $this->assertSame(1, $id->namespace);
        $this->assertSame("MyVariable", $id->identifier);
        $this->assertSame(7 + strlen($str), $id->getEncodingLength());
    }

    public function testNodeIdFourByte(): void
    {
        $id  = new NodeId(100, 1000);
        $enc = $id->encode();
        $this->assertSame(0x01, ord($enc[0])); // FourByte form
        $this->assertSame(4, $id->getEncodingLength());
        $this->assertSame(4, strlen($enc));
    }

    public function testNodeIdDecodeFourByte(): void
    {
        $raw = chr(0x01) . chr(100) . pack('v', 1000);
        $id  = NodeId::decode($raw);
        $this->assertSame(100, $id->namespace);
        $this->assertSame(1000, $id->identifier);
    }

    public function testStatusCode(): void
    {
        $good = new StatusCode(StatusCode::GOOD);
        $this->assertTrue($good->isGood());
        $this->assertSame(4, strlen($good->encode()));

        $bad = new StatusCode(StatusCode::BAD_NODE_ID_UNKNOWN);
        $this->assertTrue($bad->isBad());
    }

    public function testStatusCodeDecode(): void
    {
        $raw = pack('V', StatusCode::BAD_TIMEOUT);
        $sc  = StatusCode::decode($raw);
        $this->assertTrue($sc->isBad());
        $this->assertSame(StatusCode::BAD_TIMEOUT, $sc->code);
    }

    public function testVariantInt32(): void
    {
        $v   = Variant::int32(42);
        $enc = $v->encode();
        $this->assertSame(5, strlen($enc)); // 1 byte mask + 4 byte int32
        $this->assertSame(6, ord($enc[0])); // type INT32 = 6
        $this->assertSame(42, unpack('V', substr($enc, 1, 4))[1]);
    }

    public function testVariantDouble(): void
    {
        $v   = Variant::double(3.14);
        $enc = $v->encode();
        $this->assertSame(9, strlen($enc)); // 1 byte mask + 8 byte double
    }

    public function testVariantString(): void
    {
        $v   = Variant::string("temperature");
        $enc = $v->encode();
        $this->assertSame(0x0C, ord($enc[0])); // type STRING = 12
    }

    public function testVariantNull(): void
    {
        $v   = Variant::null();
        $enc = $v->encode();
        $this->assertSame(1, strlen($enc));
        $this->assertSame(0, ord($enc[0])); // type NULL = 0
    }

    public function testVariantNodeId(): void
    {
        $nodeId = new NodeId(0, 225);
        $v      = Variant::nodeId($nodeId);
        $enc    = $v->encode();
        $this->assertSame(0x0F, ord($enc[0])); // type NODE_ID = 15
    }

    public function testEncoderDecoderRoundtrip(): void
    {
        $enc = new BinaryEncoder();
        $enc->writeInt32(42)
            ->writeString("hello")
            ->writeDouble(3.14)
            ->writeBoolean(true);

        $dec = new BinaryDecoder($enc->toBytes());
        $this->assertSame(42, $dec->readInt32());
        $this->assertSame("hello", $dec->readString());
        $this->assertSame(3.14, $dec->readDouble());
        $this->assertTrue($dec->readBoolean());
    }

    public function testEncoderDecoderMultipleTypes(): void
    {
        $enc = new BinaryEncoder();
        $enc->writeBoolean(false)
            ->writeByte(0xAB)
            ->writeSByte(-100)
            ->writeInt16(-1234)
            ->writeUInt16(65000)
            ->writeFloat(1.5)
            ->writeString("test");

        $dec = new BinaryDecoder($enc->toBytes());
        $this->assertFalse($dec->readBoolean());
        $this->assertSame(0xAB, $dec->readByte());
        $this->assertSame(-100, $dec->readSByte());
        $this->assertSame(-1234, $dec->readInt16());
        $this->assertSame(65000, $dec->readUInt16());
        $this->assertSame(1.5, $dec->readFloat());
        $this->assertSame("test", $dec->readString());
    }

    public function testNodeIdToString(): void
    {
        $this->assertSame("i=225", (new NodeId(0, 225))->toString());
        $this->assertSame("ns=2;i=5000", (new NodeId(2, 5000))->toString());
        $this->assertSame("ns=1;s=MyVariable", (new NodeId(1, "MyVariable"))->toString());
    }

    public function testEncoderNodeIdStatusCodeRoundtrip(): void
    {
        $nodeId = new NodeId(0, 225);
        $statusCode = new StatusCode(StatusCode::BAD_NODE_ID_UNKNOWN);

        $enc = new BinaryEncoder();
        $enc->writeNodeId($nodeId);
        $enc->writeStatusCode($statusCode);

        $dec = new BinaryDecoder($enc->toBytes());
        $decodedNodeId = $dec->readNodeId();
        $decodedStatusCode = $dec->readStatusCode();

        $this->assertSame(0, $decodedNodeId->namespace);
        $this->assertSame(225, $decodedNodeId->identifier);
        $this->assertTrue($decodedStatusCode->isBad());
        $this->assertSame(StatusCode::BAD_NODE_ID_UNKNOWN, $decodedStatusCode->code);
        $this->assertSame(0, $dec->remaining());
    }

    public function testEncoderWriteBytes(): void
    {
        $enc = new BinaryEncoder();
        $enc->writeBytes("\x01\x02\x03");
        $this->assertSame(3, strlen($enc->toBytes()));
    }

    public function testStatusCodeGood(): void
    {
        $sc = new StatusCode(0x00000000);
        $this->assertTrue($sc->isGood());
        $this->assertFalse($sc->isBad());
    }

    public function testStatusCodeBad(): void
    {
        $sc = new StatusCode(0x80010000);
        $this->assertFalse($sc->isGood());
        $this->assertTrue($sc->isBad());
    }

    public function testNodeIdLargeNumeric(): void
    {
        // namespace > 255 forces Numeric form even if id <= 65535
        $id = new NodeId(300, 1000);
        $enc = $id->encode();
        $this->assertSame(0x02, ord($enc[0])); // Numeric form
        $this->assertSame(7, strlen($enc));
    }
}
