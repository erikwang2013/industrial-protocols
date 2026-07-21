<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Vme\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Vme\VmeProtocol;
use PHPUnit\Framework\TestCase;

class VmeTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new VmeProtocol();
        $this->assertSame('vme', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new VmeProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new VmeProtocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
