<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\KLine\Tests\Unit;

use Erikwang2013\IndustrialProtocols\KLine\Frame\KLineFrame;
use PHPUnit\Framework\TestCase;

class KLineTest extends TestCase
{
    public function testShortFrameFormat(): void
    {
        // Short format: Fmt=(len<<2)|addrMode, Tgt=0x33, Src=0xF1, Data=[0x01,0x00], CS
        $frame = new KLineFrame(0x33, 0xF1, [0x01, 0x00], KLineFrame::ADDR_PHYSICAL);
        $bytes = $frame->toBytes();

        // Fmt = (2 << 2) | 1 = 0x09
        $this->assertSame(0x09, ord($bytes[0]));
        $this->assertSame(0x33, ord($bytes[1]));  // Tgt
        $this->assertSame(0xF1, ord($bytes[2]));  // Src
        $this->assertSame(0x01, ord($bytes[3]));  // Data[0]
        $this->assertSame(0x00, ord($bytes[4]));  // Data[1]
        // CS = 0x09 + 0x33 + 0xF1 + 0x01 + 0x00 = 0x12E & 0xFF = 0x2E
        $this->assertSame(0x2E, ord($bytes[5]));
    }

    public function testFromBytesShortFrame(): void
    {
        $bytes = chr(0x09) . chr(0x33) . chr(0xF1) . chr(0x01) . chr(0x00) . chr(0x2E);
        $frame = KLineFrame::fromBytes($bytes);

        $this->assertSame(0x33, $frame->getTarget());
        $this->assertSame(0xF1, $frame->getSource());
        $this->assertSame(KLineFrame::ADDR_PHYSICAL, $frame->getAddrMode());
        $this->assertSame([0x01, 0x00], $frame->getRawData());
        $this->assertSame(0x01, $frame->getServiceId());
    }

    public function testChecksumMismatchThrows(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\KLine\Exception\KLineException::class);
        // Bad checksum: last byte should be 0x2E, but we put 0x00
        $bytes = chr(0x09) . chr(0x33) . chr(0xF1) . chr(0x01) . chr(0x00) . chr(0x00);
        KLineFrame::fromBytes($bytes);
    }

    public function testGetData(): void
    {
        $frame = new KLineFrame(0x33, 0xF1, [0x01, 0x0C], KLineFrame::ADDR_PHYSICAL);
        $data = $frame->getData();

        $this->assertSame(0x33, $data['target']);
        $this->assertSame(0xF1, $data['source']);
        $this->assertSame(KLineFrame::ADDR_PHYSICAL, $data['addr_mode']);
        $this->assertSame([0x01, 0x0C], $data['data']);
    }

    public function testFunctionalAddressing(): void
    {
        $frame = new KLineFrame(0x33, 0xF1, [0x01, 0x00], KLineFrame::ADDR_FUNCTIONAL);
        $bytes = $frame->toBytes();

        // Fmt = (2 << 2) | 2 = 0x0A
        $this->assertSame(0x0A, ord($bytes[0]));
    }

    public function testFrameTooShort(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\KLine\Exception\KLineException::class);
        KLineFrame::fromBytes(chr(0x09));
    }
}
