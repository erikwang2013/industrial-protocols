<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolRegistry;
use PHPUnit\Framework\TestCase;

class ProtocolRegistryTest extends TestCase
{
    public function testRegisterAndGetProtocol(): void
    {
        $registry = new ProtocolRegistry();
        $protocol = $this->createMock(ProtocolInterface::class);
        $protocol->method('getName')->willReturn('modbus');

        $registry->register($protocol);
        $this->assertSame($protocol, $registry->get('modbus'));
    }

    public function testGetAllProtocols(): void
    {
        $registry = new ProtocolRegistry();
        $p1 = $this->createMock(ProtocolInterface::class);
        $p1->method('getName')->willReturn('modbus');
        $p2 = $this->createMock(ProtocolInterface::class);
        $p2->method('getName')->willReturn('opcua');

        $registry->register($p1);
        $registry->register($p2);

        $all = $registry->all();
        $this->assertCount(2, $all);
        $this->assertSame($p1, $all['modbus']);
        $this->assertSame($p2, $all['opcua']);
    }

    public function testHasReturnsCorrectly(): void
    {
        $registry = new ProtocolRegistry();
        $this->assertFalse($registry->has('modbus'));

        $protocol = $this->createMock(ProtocolInterface::class);
        $protocol->method('getName')->willReturn('modbus');
        $registry->register($protocol);

        $this->assertTrue($registry->has('modbus'));
    }

    public function testGetThrowsIfNotFound(): void
    {
        $registry = new ProtocolRegistry();
        $this->expectException(\RuntimeException::class);
        $registry->get('nonexistent');
    }
}
