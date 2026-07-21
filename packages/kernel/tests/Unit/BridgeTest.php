<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Bridge\ExternalProcessBridge;
use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use PHPUnit\Framework\TestCase;

class BridgeTest extends TestCase
{
    public function testExternalProcessBridgeType(): void
    {
        $bridge = new ExternalProcessBridge('echo');
        $this->assertSame('external-process', $bridge->getType());
        $this->assertFalse($bridge->isReady());
    }

    public function testTcpGatewayBridgeType(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $this->assertSame('tcp-gateway', $bridge->getType());
        $this->assertFalse($bridge->isReady());
    }

    public function testBridgeConnectorDelegation(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $connector = new BridgeConnector($bridge, 'ethercat');
        $this->assertFalse($connector->isConnected());
        $health = $connector->getHealth();
        $this->assertSame('CLOSED', $health->state->value);
    }

    public function testBridgeConnectorGetBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $connector = new BridgeConnector($bridge, 'powerlink');
        $this->assertSame($bridge, $connector->getBridge());
    }
}
