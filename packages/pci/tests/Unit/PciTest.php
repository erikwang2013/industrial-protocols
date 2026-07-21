<?php

namespace Erikwang2013\IndustrialProtocols\Pci\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Pci\PciProtocol;
use PHPUnit\Framework\TestCase;

class PciTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new PciProtocol();
        $this->assertSame('pci', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new PciProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $this->assertFalse((new PciProtocol())->createConnector(['bridge' => new TcpGatewayBridge('127.0.0.1', 9999)])->isConnected());
    }
}
