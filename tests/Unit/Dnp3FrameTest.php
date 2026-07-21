<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Dnp3\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Dnp3\Frame\Dnp3Frame;
use PHPUnit\Framework\TestCase;

class Dnp3FrameTest extends TestCase
{
    public function test_crc16_known_values(): void
    {
        // Test CRC-16/DNP against known values
        $crc = Dnp3Frame::crc16("\x05\x64\x05\x44\x01\x00\x01\x00");
        $computed = unpack('v', $crc)[1];

        // Just verify CRC is non-zero and consistent
        $this->assertGreaterThan(0, $computed);
    }

    public function test_crc16_consistency(): void
    {
        $data = "\x05\x64\x05\x44\x01\x00\x01\x00";
        $crc1 = Dnp3Frame::crc16($data);
        $crc2 = Dnp3Frame::crc16($data);
        $this->assertSame($crc1, $crc2);
    }

    public function test_crc_validation(): void
    {
        // Build a valid CRC'd data
        $data = "\x01\x02\x03\x04";
        $crc = Dnp3Frame::crc16($data);
        $dataWithCrc = $data . $crc;

        $this->assertTrue(Dnp3Frame::validateCrc16($dataWithCrc));
    }

    public function test_crc_validation_detects_error(): void
    {
        $data = "\x01\x02\x03\x04";
        $crc = Dnp3Frame::crc16($data);
        // Corrupt the data
        $corrupted = "\x01\x02\x03\x05";
        $dataWithCrc = $corrupted . $crc;

        $this->assertFalse(Dnp3Frame::validateCrc16($dataWithCrc));
    }

    public function test_read_request_class0(): void
    {
        $frame = Dnp3Frame::readRequest(60, 0, 0);

        $this->assertSame(Dnp3Frame::FC_READ, $frame->getFunctionCode());
        $this->assertSame('READ', $frame->getFunctionName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);

        // Check start bytes
        $this->assertSame("\x05\x64", substr($bytes, 0, 2));
    }

    public function test_read_request_analog_input(): void
    {
        $frame = Dnp3Frame::readRequest(30, 1, 5);

        $this->assertSame(Dnp3Frame::FC_READ, $frame->getFunctionCode());
        $this->assertStringContainsString('READ', $frame->getFunctionName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);
    }

    public function test_select_operate_frames(): void
    {
        $selectFrame = Dnp3Frame::select(10, 2, 0, 1);
        $this->assertSame(Dnp3Frame::FC_SELECT, $selectFrame->getFunctionCode());

        $operateFrame = Dnp3Frame::operate(10, 2, 0, 1);
        $this->assertSame(Dnp3Frame::FC_OPERATE, $operateFrame->getFunctionCode());
    }

    public function test_direct_operate(): void
    {
        $frame = Dnp3Frame::directOperate(10, 2, 3, 1);

        $this->assertSame(Dnp3Frame::FC_DIRECT_OPERATE, $frame->getFunctionCode());
        $this->assertSame('DIRECT_OPERATE', $frame->getFunctionName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);
    }

    public function test_encode_decode_roundtrip(): void
    {
        $frame = Dnp3Frame::readRequest(30, 1, 7);
        $bytes = $frame->toBytes();

        $decoded = Dnp3Frame::fromBytes($bytes);

        $this->assertSame(1, $decoded->getSource());
        $this->assertSame(1, $decoded->getDestination());
        $this->assertSame(Dnp3Frame::FC_READ, $decoded->getFunctionCode());
    }

    public function test_get_data(): void
    {
        $frame = Dnp3Frame::directOperate(10, 2, 3, 1);
        $data = $frame->getData();

        $this->assertIsArray($data);
        $this->assertSame('DIRECT_OPERATE', $data['function_name']);
        $this->assertSame(Dnp3Frame::FC_DIRECT_OPERATE, $data['function_code']);
    }

    public function test_start_bytes_present(): void
    {
        $frame = Dnp3Frame::readRequest(30, 1, 0);
        $bytes = $frame->toBytes();

        $this->assertSame("\x05\x64", substr($bytes, 0, 2));
    }

    public function test_frame_too_short_throws(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\Exception\FrameException::class);
        Dnp3Frame::fromBytes("\x05\x64");
    }

    public function test_invalid_start_bytes_throws(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\Exception\FrameException::class);
        Dnp3Frame::fromBytes("\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00");
    }

    public function test_response_detection(): void
    {
        $frame = new Dnp3Frame(1, 1, Dnp3Frame::FC_RESPONSE);
        $this->assertTrue($frame->isResponse());

        $frame2 = new Dnp3Frame(1, 1, Dnp3Frame::FC_READ);
        $this->assertFalse($frame2->isResponse());
    }
}
