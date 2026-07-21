<?php

namespace Erikwang2013\IndustrialProtocols\Lightbus\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Lightbus\LightbusProtocol;
use PHPUnit\Framework\TestCase;

class LightbusTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new LightbusProtocol();
        $this->assertSame('lightbus', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LightbusProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new LightbusProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
