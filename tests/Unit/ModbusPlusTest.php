<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\ModbusPlus\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\ModbusPlus\ModbusPlusProtocol;
use PHPUnit\Framework\TestCase;

class ModbusPlusTest extends TestCase
{
    public function testMetadata(): void
    {
        $p = new ModbusPlusProtocol();
        $this->assertSame('modbus-plus', $p->getName());
        $this->assertSame('1.0.0', $p->getVersion());
    }

    public function testRequiresBridge(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ModbusPlusProtocol())->createConnector([]);
    }

    public function testWithBridge(): void
    {
        $bridge = new TcpGatewayBridge('127.0.0.1', 9999);
        $c = (new ModbusPlusProtocol())->createConnector(['bridge' => $bridge]);
        $this->assertFalse($c->isConnected());
    }
}
