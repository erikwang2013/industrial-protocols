<?php

namespace Erikwang2013\IndustrialProtocols\Sercos\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Sercos\SercosProtocol;
use PHPUnit\Framework\TestCase;

class SercosTest extends TestCase
{
    public function testProtocolMetadata(): void
    {
        $p = new SercosProtocol();
        $this->assertSame('sercos', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
        $this->assertContains('bridge', $p->getSupportedVariants());
    }

    public function testRequiresBridge(): void
    {
        $p = new SercosProtocol();
        $this->expectException(\RuntimeException::class);
        $p->createConnector([]);
    }

    public function testCreateConnectorWithBridge(): void
    {
        $p = new SercosProtocol();
        $bridge = new TcpGatewayBridge('192.168.1.100', 5555);
        $conn = $p->createConnector(['bridge' => $bridge]);
        $this->assertFalse($conn->isConnected());
    }
}
