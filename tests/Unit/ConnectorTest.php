<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Tests\Unit;

use Erikwang2013\IndustrialProtocols\OpcUa\OpcUaConnector;
use Erikwang2013\IndustrialProtocols\OpcUa\OpcUaProtocol;
use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase
{
    public function testProtocolMetadata(): void
    {
        $protocol = new OpcUaProtocol();
        $this->assertSame('opc-ua', $protocol->getName());
        $this->assertSame('1.0.0', $protocol->getVersion());
        $this->assertSame(4840, $protocol->getDefaultPort());
        $this->assertContains('binary', $protocol->getSupportedVariants());
    }

    public function testProtocolCreateConnector(): void
    {
        $protocol = new OpcUaProtocol();
        $connector = $protocol->createConnector([
            'host' => '192.168.1.10',
            'port' => 4840,
        ]);
        $this->assertInstanceOf(OpcUaConnector::class, $connector);
    }

    public function testParseNodeIdNumeric(): void
    {
        $connector = new OpcUaConnector([]);
        $ref = new \ReflectionMethod($connector, 'parseNodeId');

        $id = $ref->invoke($connector, 'ns=2;i=5000');
        $this->assertSame(2, $id->namespace);
        $this->assertSame(5000, $id->identifier);

        $id = $ref->invoke($connector, 'i=2258');
        $this->assertSame(0, $id->namespace);
        $this->assertSame(2258, $id->identifier);

        $id = $ref->invoke($connector, 'ns=1;s=Temperature');
        $this->assertSame(1, $id->namespace);
        $this->assertSame('Temperature', $id->identifier);
    }

    public function testHealthBeforeConnect(): void
    {
        $connector = new OpcUaConnector([]);
        $health = $connector->getHealth();
        $this->assertSame('CLOSED', $health->state->value);
    }

    public function testConnectorWithoutSession(): void
    {
        $connector = new OpcUaConnector([]);
        $this->assertFalse($connector->isConnected());
    }
}
