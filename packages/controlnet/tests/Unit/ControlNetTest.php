<?php

namespace Erikwang2013\IndustrialProtocols\ControlNet\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\ControlNet\ControlNetProtocol;
use PHPUnit\Framework\TestCase;

class ControlNetTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new ControlNetProtocol();
        $this->assertSame('controlnet', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ControlNetProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new ControlNetProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
