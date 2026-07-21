<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Exception\ConnectionException;
use IndustrialProtocols\Exception\ConnectionTimeoutException;
use IndustrialProtocols\Exception\ConnectionRefusedException;
use IndustrialProtocols\Exception\ConnectionClosedException;
use IndustrialProtocols\Exception\ProtocolException;
use IndustrialProtocols\Exception\FrameException;
use IndustrialProtocols\Exception\CrcException;
use IndustrialProtocols\Exception\DeviceException;
use IndustrialProtocols\Exception\DeviceBusyException;
use IndustrialProtocols\Exception\AddressOutOfRangeException;
use IndustrialProtocols\Exception\IndustrialProtocolsException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testConnectionTimeoutIsConnectionException(): void
    {
        $e = new ConnectionTimeoutException('timeout');
        $this->assertInstanceOf(ConnectionException::class, $e);
        $this->assertInstanceOf(IndustrialProtocolsException::class, $e);
    }

    public function testConnectionRefusedIsConnectionException(): void
    {
        $e = new ConnectionRefusedException('refused');
        $this->assertInstanceOf(ConnectionException::class, $e);
    }

    public function testConnectionClosedIsConnectionException(): void
    {
        $e = new ConnectionClosedException('closed');
        $this->assertInstanceOf(ConnectionException::class, $e);
    }

    public function testFrameExceptionIsProtocolException(): void
    {
        $e = new FrameException('bad frame');
        $this->assertInstanceOf(ProtocolException::class, $e);
    }

    public function testCrcExceptionIsProtocolException(): void
    {
        $e = new CrcException('crc mismatch');
        $this->assertInstanceOf(ProtocolException::class, $e);
    }

    public function testDeviceBusyIsDeviceException(): void
    {
        $e = new DeviceBusyException('busy');
        $this->assertInstanceOf(DeviceException::class, $e);
    }

    public function testAddressOutOfRangeIsDeviceException(): void
    {
        $e = new AddressOutOfRangeException('out of range');
        $this->assertInstanceOf(DeviceException::class, $e);
    }

    public function testExceptionCarriesContext(): void
    {
        $e = new ConnectionTimeoutException('timeout', ['device' => 'plc-001', 'host' => '192.168.1.10']);
        $this->assertSame('plc-001', $e->getContext()['device']);
    }
}
