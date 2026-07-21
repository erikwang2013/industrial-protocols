<?php

namespace Erikwang2013\IndustrialProtocols\LonWorks\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\LonWorks\LonWorksProtocol;
use PHPUnit\Framework\TestCase;

class LonWorksTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new LonWorksProtocol();
        $this->assertSame('lonworks', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LonWorksProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new LonWorksProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
