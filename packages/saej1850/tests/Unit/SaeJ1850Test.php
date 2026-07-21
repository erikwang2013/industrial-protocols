<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\SaeJ1850\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\SaeJ1850\SaeJ1850Protocol;
use PHPUnit\Framework\TestCase;

class SaeJ1850Test extends TestCase
{
    public function testMetadata(): void
    {
        $p = new SaeJ1850Protocol();
        $this->assertSame('sae-j1850', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SaeJ1850Protocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new SaeJ1850Protocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
