<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Sercos1\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Sercos1\Sercos1Protocol;
use PHPUnit\Framework\TestCase;

class Sercos1Test extends TestCase
{
    public function testMetadata(): void
    {
        $p = new Sercos1Protocol();
        $this->assertSame('sercos-i', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new Sercos1Protocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new Sercos1Protocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
