<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Profibus\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Profibus\ProfibusProtocol;
use PHPUnit\Framework\TestCase;

class ProfibusTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new ProfibusProtocol();
        $this->assertSame('profibus', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ProfibusProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new ProfibusProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
