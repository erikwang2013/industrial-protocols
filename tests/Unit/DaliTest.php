<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Dali\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Dali\DaliProtocol;
use PHPUnit\Framework\TestCase;

class DaliTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new DaliProtocol();
        $this->assertSame('dali', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new DaliProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new DaliProtocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
