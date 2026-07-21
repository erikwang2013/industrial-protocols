<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Profinet\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Profinet\Frame\ProfinetFrame;
use Erikwang2013\IndustrialProtocols\Profinet\ProfinetProtocol;
use Erikwang2013\IndustrialProtocols\Profinet\ProfinetConnector;
use PHPUnit\Framework\TestCase;

class ProfinetFrameTest extends TestCase
{
    public function testDcpIdentifyFrame(): void
    {
        $frame = ProfinetFrame::dcpIdentify();
        $bytes = $frame->toBytes();
        $this->assertSame(0xFE, ord($bytes[0]));
        $this->assertSame(0xFE, ord($bytes[1])); // Frame ID 0xFEFE
    }

    public function testReadRecordFrame(): void
    {
        $frame = ProfinetFrame::readRecord(0, 0, 1, 0xAFF0);
        $bytes = $frame->toBytes();
        $this->assertSame(0xFD, ord($bytes[0]));
        $this->assertSame(0xFE, ord($bytes[1])); // Frame ID 0xFEFD
    }

    public function testFrameRoundTrip(): void
    {
        $original = ProfinetFrame::dcpIdentify();
        $parsed = ProfinetFrame::fromBytes($original->toBytes());
        $data = $parsed->getData();
        $this->assertSame(0xFEFE, $data['frame_id']);
    }

    public function testProtocolMetadata(): void
    {
        $protocol = new ProfinetProtocol();
        $this->assertSame('profinet', $protocol->getName());
        $this->assertSame('1.0.0', $protocol->getVersion());
        $this->assertSame(34964, $protocol->getDefaultPort());
        $this->assertContains('nrt', $protocol->getSupportedVariants());
    }

    public function testProtocolCreateConnector(): void
    {
        $protocol = new ProfinetProtocol();
        $connector = $protocol->createConnector(['host' => '192.168.1.10']);
        $this->assertInstanceOf(ProfinetConnector::class, $connector);
    }

    public function testConnectorHealthBeforeConnect(): void
    {
        $connector = new ProfinetConnector([]);
        $health = $connector->getHealth();
        $this->assertSame('CLOSED', $health->state->value);
    }
}
