<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Most\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Most\MostProtocol;
use PHPUnit\Framework\TestCase;

class MostTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new MostProtocol();
        $this->assertSame('most', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new MostProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new MostProtocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
