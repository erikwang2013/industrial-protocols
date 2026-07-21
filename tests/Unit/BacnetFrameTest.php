<?php

namespace IndustrialProtocols\Bacnet\Tests\Unit;

use IndustrialProtocols\Bacnet\Frame\BacnetFrame;
use PHPUnit\Framework\TestCase;

class BacnetFrameTest extends TestCase
{
    public function testWhoIsFrame(): void
    {
        $frame = BacnetFrame::whoIs();
        $bytes = $frame->toBytes();
        $this->assertGreaterThan(4, strlen($bytes));
        $this->assertSame(0x81, ord($bytes[0])); // BVLL type
        $this->assertSame(0x0B, ord($bytes[1])); // broadcast
    }

    public function testReadPropertyFrame(): void
    {
        $frame = BacnetFrame::readProperty(1234, 0, 1, 85);
        $bytes = $frame->toBytes();
        $this->assertSame(0x81, ord($bytes[0]));
        $this->assertSame(0x0A, ord($bytes[1])); // unicast
    }

    public function testFrameRoundTrip(): void
    {
        $original = BacnetFrame::whoIs();
        $parsed = BacnetFrame::fromBytes($original->toBytes());
        $this->assertIsArray($parsed->getData());
    }

    public function testIAmFrame(): void
    {
        $frame = BacnetFrame::iAm(1234, 1476, 0, 999);
        $bytes = $frame->toBytes();
        $this->assertGreaterThan(4, strlen($bytes));
    }
}
