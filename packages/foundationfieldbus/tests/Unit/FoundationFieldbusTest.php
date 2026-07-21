<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\FoundationFieldbus\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\FoundationFieldbus\FoundationFieldbusProtocol;
use PHPUnit\Framework\TestCase;

class FoundationFieldbusTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new FoundationFieldbusProtocol();
        $this->assertSame('foundation-fieldbus', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FoundationFieldbusProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new FoundationFieldbusProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
