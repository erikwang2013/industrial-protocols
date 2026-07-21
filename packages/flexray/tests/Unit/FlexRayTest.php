<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\FlexRay\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\FlexRay\FlexRayProtocol;
use PHPUnit\Framework\TestCase;

class FlexRayTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new FlexRayProtocol();
        $this->assertSame('flexray', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new FlexRayProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new FlexRayProtocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
