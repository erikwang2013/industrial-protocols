<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Protocol\ConnectorInterface;
use IndustrialProtocols\Protocol\DataPointInterface;
use IndustrialProtocols\Protocol\DriverInterface;
use IndustrialProtocols\Protocol\FrameInterface;
use IndustrialProtocols\Protocol\GatewayRuleInterface;
use IndustrialProtocols\Protocol\ProtocolInterface;
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
