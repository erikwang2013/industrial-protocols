<?php

namespace Erikwang2013\IndustrialProtocols\AsInterface\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\AsInterface\AsInterfaceProtocol;
use PHPUnit\Framework\TestCase;

class AsInterfaceTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new AsInterfaceProtocol();
        $this->assertSame('as-interface', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AsInterfaceProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new AsInterfaceProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
