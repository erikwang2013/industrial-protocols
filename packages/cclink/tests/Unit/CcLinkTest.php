<?php

namespace Erikwang2013\IndustrialProtocols\CcLink\Tests\Unit;

use Erikwang2013\IndustrialProtocols\CcLink\CcLinkProtocol;
use PHPUnit\Framework\TestCase;

class CcLinkTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new CcLinkProtocol();
        $this->assertSame('cc-link', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
        $this->assertContains('rs485', $p->getSupportedVariants());
        $this->assertSame(0, $p->getDefaultPort());
    }

    public function testCreateConnector(): void
    {
        $p = new CcLinkProtocol();
        $c = $p->createConnector(['device' => '/dev/null']);
        $this->assertFalse($c->isConnected());
    }
}
