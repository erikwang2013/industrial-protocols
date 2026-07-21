<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\DataPointInterface;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;
use Erikwang2013\IndustrialProtocols\Protocol\GatewayRuleInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;
use PHPUnit\Framework\TestCase;

class InterfaceExistsTest extends TestCase
{
    public function testAllInterfacesAreDefined(): void
    {
        $this->assertTrue(interface_exists(ProtocolInterface::class));
        $this->assertTrue(interface_exists(ConnectorInterface::class));
        $this->assertTrue(interface_exists(DriverInterface::class));
        $this->assertTrue(interface_exists(FrameInterface::class));
        $this->assertTrue(interface_exists(DataPointInterface::class));
        $this->assertTrue(interface_exists(GatewayRuleInterface::class));
    }
}
