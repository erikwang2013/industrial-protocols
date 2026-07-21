<?php

namespace Erikwang2013\IndustrialProtocols\WirelessHart\Tests\Unit;

use Erikwang2013\IndustrialProtocols\WirelessHart\WirelessHartProtocol;
use PHPUnit\Framework\TestCase;

class WirelessHartProtocolTest extends TestCase
{
    public function test_get_name(): void
    {
        $protocol = new WirelessHartProtocol();
        $this->assertSame('wirelesshart', $protocol->getName());
    }

    public function test_get_version(): void
    {
        $protocol = new WirelessHartProtocol();
        $this->assertSame('1.0.0', $protocol->getVersion());
    }

    public function test_get_supported_variants(): void
    {
        $protocol = new WirelessHartProtocol();
        $variants = $protocol->getSupportedVariants();
        $this->assertContains('wireless', $variants);
        $this->assertContains('gateway', $variants);
    }

    public function test_get_default_port_returns_zero(): void
    {
        $protocol = new WirelessHartProtocol();
        $this->assertSame(0, $protocol->getDefaultPort());
    }

    public function test_create_connector_without_bridge_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BridgeInterface');

        $protocol = new WirelessHartProtocol();
        $protocol->createConnector([]);
    }
}
