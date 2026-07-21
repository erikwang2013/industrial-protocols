<?php

namespace Erikwang2013\IndustrialProtocols\Hart\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Hart\HartProtocol;
use PHPUnit\Framework\TestCase;

class HartTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new HartProtocol();
        $this->assertSame('hart', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
        $this->assertContains('serial', $p->getSupportedVariants());
        $this->assertContains('multidrop', $p->getSupportedVariants());
        $this->assertSame(0, $p->getDefaultPort());
    }

    public function testCreateConnector(): void
    {
        $p = new HartProtocol();
        $c = $p->createConnector(['device' => '/dev/null']);
        $this->assertFalse($c->isConnected());
    }

    public function testCreateConnectorWithDefaults(): void
    {
        $p = new HartProtocol();
        $c = $p->createConnector([]);
        $this->assertFalse($c->isConnected());
    }
}
