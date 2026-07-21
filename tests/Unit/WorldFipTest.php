<?php

namespace Erikwang2013\IndustrialProtocols\WorldFip\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\WorldFip\WorldFipProtocol;
use PHPUnit\Framework\TestCase;

class WorldFipTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new WorldFipProtocol();
        $this->assertSame('worldfip', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new WorldFipProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new WorldFipProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
