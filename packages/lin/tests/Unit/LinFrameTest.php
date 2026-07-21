<?php

namespace Erikwang2013\IndustrialProtocols\Lin\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Lin\Frame\LinFrame;
use PHPUnit\Framework\TestCase;

class LinFrameTest extends TestCase
{
    public function testPidComputation(): void
    {
        // ID 0x10: P0 = parity of bits 0,1,2,4 = 0^0^0^1 = 1, P1 = !(parity of 1,3,4,5) = !(0^0^1^0) = !1 = 0
        // PID = (0<<7) | (1<<6) | 0x10 = 0x50
        $this->assertSame(0x50, LinFrame::computePid(0x10));

        // ID 0x0C: P0 = parity of bits 0,1,2,4 = 0^0^1^0 = 1, P1 = !(parity of 1,3,4,5) = !(0^1^0^0) = !1 = 0
        // PID = (0<<7) | (1<<6) | 0x0C = 0x4C
        $this->assertSame(0x4C, LinFrame::computePid(0x0C));
    }

    public function testPidVerification(): void
    {
        $this->assertTrue(LinFrame::verifyPid(0x50));
        $this->assertTrue(LinFrame::verifyPid(0x4C));
        $this->assertFalse(LinFrame::verifyPid(0x10));  // Raw ID, no parity bits
    }

    public function testPidToId(): void
    {
        $this->assertSame(0x10, LinFrame::pidToId(0x50));
        $this->assertSame(0x0C, LinFrame::pidToId(0x4C));
    }

    public function testPidToIdBadParityThrows(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\Lin\Exception\LinException::class);
        LinFrame::pidToId(0x10);  // No parity bits set
    }

    public function testClassicChecksum(): void
    {
        // Classic checksum: (0xFF - sum) mod 256 (with carry-safe addition)
        $cs = LinFrame::classicChecksum([0x01, 0x02, 0x03]);
        // Sum = 1+2+3 = 6, checksum = 255 - 6 = 249 = 0xF9
        $this->assertSame(0xF9, $cs);
    }

    public function testEnhancedChecksum(): void
    {
        // Enhanced checksum = classicChecksum(PID + data)
        $pid = LinFrame::computePid(0x10);  // 0x50
        $cs = LinFrame::enhancedChecksum($pid, [0x01, 0x02]);
        // Sum = 0x50 + 1 + 2 = 80+3 = 83, checksum = 255-83 = 172 = 0xAC
        $this->assertSame(0xAC, $cs);
    }

    public function testToBytes(): void
    {
        $frame = new LinFrame(0x10, [0x01, 0x02, 0x03], LinFrame::CLASSIC_CHECKSUM);
        $bytes = $frame->toBytes();
        $this->assertEquals(5, strlen($bytes));
        $this->assertSame(0x50, ord($bytes[0]));  // PID
        $this->assertSame(0x01, ord($bytes[1]));  // Data 0
        $this->assertSame(0x02, ord($bytes[2]));  // Data 1
        $this->assertSame(0x03, ord($bytes[3]));  // Data 2
        $this->assertSame(0xF9, ord($bytes[4]));  // Classic checksum
    }

    public function testFromBytes(): void
    {
        $bytes = chr(0x50) . chr(0x01) . chr(0x02) . chr(0x03) . chr(0xF9);
        $frame = LinFrame::fromBytes($bytes);
        $this->assertSame(0x10, $frame->getId());
        $this->assertSame([0x01, 0x02, 0x03], $frame->getRawData());
        $this->assertSame(LinFrame::CLASSIC_CHECKSUM, $frame->getChecksumType());
    }

    public function testInvalidFrameId(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\Lin\Exception\LinException::class);
        new LinFrame(64);
    }

    public function testInvalidDataLength(): void
    {
        $this->expectException(\Erikwang2013\IndustrialProtocols\Lin\Exception\LinException::class);
        new LinFrame(0, array_fill(0, 9, 0xFF));
    }

    public function testGetData(): void
    {
        $frame = new LinFrame(0x10, [0x01, 0x02]);
        $data = $frame->getData();
        $this->assertSame(0x10, $data['id']);
        $this->assertSame(0x50, $data['pid']);
        $this->assertSame([0x01, 0x02], $data['data']);
    }
}
