<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\EtherNetIP\Tests\Unit;

use Erikwang2013\IndustrialProtocols\EtherNetIP\Frame\EtherNetIPFrame;
use PHPUnit\Framework\TestCase;

class EtherNetIPFrameTest extends TestCase
{
    public function testRegisterSession(): void
    {
        $frame = EtherNetIPFrame::registerSession();
        $bytes = $frame->toBytes();
        $this->assertGreaterThanOrEqual(24, strlen($bytes));
        $cmd = unpack('v', substr($bytes, 0, 2))[1];
        $this->assertSame(0x0065, $cmd);
    }

    public function testReadTag(): void
    {
        $frame = EtherNetIPFrame::readTag(1, 'MyTag');
        $bytes = $frame->toBytes();
        $cmd = unpack('v', substr($bytes, 0, 2))[1];
        $this->assertSame(0x0070, $cmd);
    }

    public function testFrameRoundTrip(): void
    {
        $original = EtherNetIPFrame::registerSession();
        $parsed = EtherNetIPFrame::fromBytes($original->toBytes());
        $this->assertIsArray($parsed->getData());
    }
}
