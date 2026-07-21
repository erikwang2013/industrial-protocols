<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Powerlink\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Powerlink\PowerlinkProtocol;
use PHPUnit\Framework\TestCase;

class PowerlinkTest extends TestCase
{
    public function testProtocolMetadata(): void
    {
        $p = new PowerlinkProtocol();
        $this->assertSame('powerlink', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
        $this->assertContains('bridge', $p->getSupportedVariants());
    }

    public function testRequiresBridge(): void
    {
        $p = new PowerlinkProtocol();
        $this->expectException(\RuntimeException::class);
        $p->createConnector([]);
    }

    public function testCreateConnectorWithBridge(): void
    {
        $p = new PowerlinkProtocol();
        $bridge = new TcpGatewayBridge('192.168.1.100', 5555);
        $conn = $p->createConnector(['bridge' => $bridge]);
        $this->assertFalse($conn->isConnected());
    }
}
