<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\HartIp\Tests\Unit;

use Erikwang2013\IndustrialProtocols\HartIp\Frame\HartIpFrame;
use PHPUnit\Framework\TestCase;

class HartIpTest extends TestCase
{
    public function testRequestFrameEncoding(): void
    {
        $hartCommand = str_repeat(chr(0xFF), 5) . chr(0x82) . chr(0x00) . chr(0x01) . chr(0x00) . chr(0x83);
        $frame = HartIpFrame::request(1, 100, $hartCommand);

        $bytes = $frame->toBytes();

        // Header: version(1) + type(0) + status(0) + reserved(0)
        $this->assertSame(0x01, ord($bytes[0]));  // Version
        $this->assertSame(0x00, ord($bytes[1]));  // Type = REQUEST
        $this->assertSame(0x00, ord($bytes[2]));  // Status = OK

        // Sequence: 1 (big-endian, 2 bytes)
        $this->assertSame(0x00, ord($bytes[4]));
        $this->assertSame(0x01, ord($bytes[5]));

        // Message ID: 100 (big-endian)
        $this->assertSame(0x00, ord($bytes[6]));
        $this->assertSame(0x64, ord($bytes[7]));  // 0x64 = 100

        // Payload length
        $this->assertSame(strlen($hartCommand), ord($bytes[8]));

        // Payload starts at byte 9
        $this->assertSame($hartCommand, substr($bytes, 9));
    }

    public function testRequestFrameDecoding(): void
    {
        $hartCommand = str_repeat(chr(0xFF), 5) . chr(0x82) . chr(0x00) . chr(0x01) . chr(0x00) . chr(0x83);
        $original = HartIpFrame::request(1, 100, $hartCommand);
        $bytes = $original->toBytes();

        $parsed = HartIpFrame::fromBytes($bytes);
        $this->assertSame(HartIpFrame::TYPE_REQUEST, $parsed->getMessageType());
        $this->assertSame(1, $parsed->getSequenceNumber());
        $this->assertSame(100, $parsed->getMessageId());
        $this->assertSame($hartCommand, $parsed->getHartCommand());
        $this->assertFalse($parsed->isError());
    }

    public function testResponseFrame(): void
    {
        $hartResponse = chr(0xFF) . chr(0xFF) . chr(0x86) . chr(0x00) . chr(0x01) . chr(0x05) . chr(0x00) . chr(0x06);
        $frame = HartIpFrame::response(1, 100, $hartResponse);

        $bytes = $frame->toBytes();
        $this->assertSame(0x01, ord($bytes[1]));  // Type = RESPONSE
    }

    public function testErrorFrame(): void
    {
        $frame = new HartIpFrame(HartIpFrame::TYPE_ERROR, 1, 100, '', 0x40);
        $this->assertTrue($frame->isError());
    }

    public function testInvalidVersionThrows(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\HartIp\Exception\HartIpException::class);
        // Version 0x02 in header instead of 0x01
        $bytes = pack('CCCCnnC', 0x02, 0x00, 0x00, 0x00, 1, 100, 0);
        HartIpFrame::fromBytes($bytes);
    }

    public function testHeaderTooShortThrows(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\HartIp\Exception\HartIpException::class);
        HartIpFrame::fromBytes(chr(0x01) . chr(0x00));
    }

    public function testGetData(): void
    {
        $hartCmd = chr(0xFF) . chr(0xFF) . chr(0x82) . chr(0x00) . chr(0x01) . chr(0x00) . chr(0x83);
        $frame = HartIpFrame::request(1, 42, $hartCmd);
        $data = $frame->getData();

        $this->assertSame(1, $data['version']);
        $this->assertSame(HartIpFrame::TYPE_REQUEST, $data['message_type']);
        $this->assertSame(1, $data['sequence_number']);
        $this->assertSame(42, $data['message_id']);
    }

    public function testResponseIsNotError(): void
    {
        $frame = new HartIpFrame(HartIpFrame::TYPE_RESPONSE, 1, 100);
        $this->assertFalse($frame->isError());
    }
}
