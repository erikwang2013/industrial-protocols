<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Isa100\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Isa100\Isa100Protocol;
use PHPUnit\Framework\TestCase;

class Isa100ProtocolTest extends TestCase
{
    public function test_get_name(): void
    {
        $protocol = new Isa100Protocol();
        $this->assertSame('isa100', $protocol->getName());
    }

    public function test_get_version(): void
    {
        $protocol = new Isa100Protocol();
        $this->assertSame('1.0.0', $protocol->getVersion());
    }

    public function test_get_supported_variants(): void
    {
        $protocol = new Isa100Protocol();
        $variants = $protocol->getSupportedVariants();
        $this->assertContains('wireless', $variants);
        $this->assertContains('backbone', $variants);
    }

    public function test_get_default_port_returns_zero(): void
    {
        $protocol = new Isa100Protocol();
        $this->assertSame(0, $protocol->getDefaultPort());
    }

    public function test_create_connector_without_bridge_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BridgeInterface');

        $protocol = new Isa100Protocol();
        $protocol->createConnector([]);
    }
}
