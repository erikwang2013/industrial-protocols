<?php

namespace Erikwang2013\IndustrialProtocols\Interbus\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Interbus\InterbusProtocol;
use PHPUnit\Framework\TestCase;

class InterbusTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new InterbusProtocol();
        $this->assertSame('interbus', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new InterbusProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new InterbusProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
