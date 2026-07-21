<?php
namespace IndustrialProtocols\Tests\Unit;
use IndustrialProtocols\Security\InputValidator;
use PHPUnit\Framework\TestCase;

class InputValidatorTest extends TestCase
{
    public function testValidDeviceId(): void
    {
        $this->assertSame('plc-001', InputValidator::deviceId('plc-001'));
        $this->assertSame('device_1.prod', InputValidator::deviceId('device_1.prod'));
    }

    public function testInvalidDeviceIdEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputValidator::deviceId('');
    }

    public function testInvalidDeviceIdSpecialChars(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputValidator::deviceId('plc<script>');
    }

    public function testValidHost(): void
    {
        $this->assertSame('192.168.1.10', InputValidator::host('192.168.1.10'));
        $this->assertSame('plc.local', InputValidator::host('plc.local'));
    }

    public function testValidPort(): void
    {
        $this->assertSame(502, InputValidator::port(502));
    }

    public function testInvalidPort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputValidator::port(99999);
    }

    public function testModbusAddressRange(): void
    {
        $this->assertSame('40001', InputValidator::modbusAddress('40001'));
    }

    public function testModbusAddressOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        InputValidator::modbusAddress('99999');
    }

    public function testTimeoutRange(): void
    {
        $this->assertSame(3000, InputValidator::timeout(3000));
        $this->expectException(\InvalidArgumentException::class);
        InputValidator::timeout(5);
    }

    public function testFrameSizeLimit(): void
    {
        InputValidator::frameSize(str_repeat('x', 260)); // ok
        $this->expectException(\InvalidArgumentException::class);
        InputValidator::frameSize(str_repeat('x', 261)); // too large
    }
}
