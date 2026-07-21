<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Cpci\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Cpci\CpciProtocol;
use PHPUnit\Framework\TestCase;

class CpciTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new CpciProtocol();
        $this->assertSame('cpci', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new CpciProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new CpciProtocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
