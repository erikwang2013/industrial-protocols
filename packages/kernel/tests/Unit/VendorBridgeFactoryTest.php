<?php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Bridge\ExternalProcessBridge;
use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;
use Erikwang2013\IndustrialProtocols\Vendor\DefaultVendors;
use Erikwang2013\IndustrialProtocols\Vendor\VendorBridgeFactory;
use PHPUnit\Framework\TestCase;

class VendorBridgeFactoryTest extends TestCase
{
    private VendorBridgeFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new VendorBridgeFactory();
        DefaultVendors::register($this->factory);
    }

    public function testListVendorsReturnsAllEight(): void
    {
        $vendors = $this->factory->listVendors();
        $this->assertCount(8, $vendors);
        $this->assertArrayHasKey('beckhoff', $vendors);
        $this->assertArrayHasKey('siemens', $vendors);
        $this->assertArrayHasKey('br-automation', $vendors);
        $this->assertArrayHasKey('bosch-rexroth', $vendors);
        $this->assertArrayHasKey('hilscher', $vendors);
        $this->assertArrayHasKey('hms-anybus', $vendors);
        $this->assertArrayHasKey('moxa', $vendors);
        $this->assertArrayHasKey('phoenix-contact', $vendors);
    }

    public function testGetVendorReturnsProfile(): void
    {
        $beckhoff = $this->factory->getVendor('beckhoff');
        $this->assertNotNull($beckhoff);
        $this->assertSame('ethercat', $beckhoff->protocol);
        $this->assertSame('external-process', $beckhoff->bridgeType);
    }

    public function testGetDevicesByVendor(): void
    {
        $devices = $this->factory->getDevices('siemens');
        $this->assertCount(5, $devices);
        $this->assertSame('S7-1200', $devices[0]->model);
        $this->assertSame('V4.x', $devices[0]->version);
    }

    public function testUnknownVendorThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->factory->create('unknown-vendor');
    }

    public function testCreateBeckhoffBridge(): void
    {
        $bridge = $this->factory->create('beckhoff', 'CX2030', '3.1');
        $this->assertInstanceOf(ExternalProcessBridge::class, $bridge);
        $this->assertSame('external-process', $bridge->getType());
    }

    public function testCreateSiemensBridge(): void
    {
        $bridge = $this->factory->create('siemens', 'S7-1500', 'V3.x', [
            'host' => '192.168.1.50',
        ]);
        $this->assertInstanceOf(TcpGatewayBridge::class, $bridge);
        $this->assertFalse($bridge->isReady()); // not connected yet
    }

    public function testCreateHilscherBridgeWithDeviceOverride(): void
    {
        $bridge = $this->factory->create('hilscher', 'netX 90');
        $this->assertInstanceOf(TcpGatewayBridge::class, $bridge);
    }

    public function testDeviceVersionMatters(): void
    {
        $device = $this->factory->getVendor('beckhoff')->getDevice('CX2030');
        $this->assertSame('3.1', $device->version);
    }

    public function testMoxaGatewayBridge(): void
    {
        $bridge = $this->factory->create('moxa', 'MGate 5105-MB-EIP', 'V3.x', [
            'host' => '10.0.0.100',
        ]);
        $this->assertInstanceOf(TcpGatewayBridge::class, $bridge);
        $this->assertFalse($bridge->isReady());
    }

    public function testPhoenixContactProfinetBridge(): void
    {
        $bridge = $this->factory->create('phoenix-contact', 'AXL F BK PN');
        $this->assertInstanceOf(TcpGatewayBridge::class, $bridge);
    }
}
