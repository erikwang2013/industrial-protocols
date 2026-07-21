<?php

namespace IndustrialProtocols\Modbus\Tests\Unit;

use IndustrialProtocols\Modbus\Exception\ModbusException;
use IndustrialProtocols\Modbus\Frame\ModbusFrame;
use IndustrialProtocols\Modbus\Frame\ModbusRequest;
use IndustrialProtocols\Modbus\Frame\ModbusResponse;
use PHPUnit\Framework\TestCase;

class ModbusFrameTest extends TestCase
{
    public function testBuildReadHoldingRegistersRequest(): void
    {
        $request = ModbusRequest::readHoldingRegisters(1, 0, 1);
        $bytes = $request->toBytes();

        $this->assertSame(12, strlen($bytes));
        $this->assertSame(1, ord($bytes[6]));       // Unit ID
        $this->assertSame(0x03, ord($bytes[7]));    // FC
        $this->assertSame(0, ord($bytes[8]));       // Start addr hi
        $this->assertSame(0, ord($bytes[9]));       // Start addr lo
        $this->assertSame(0, ord($bytes[10]));      // Quantity hi
        $this->assertSame(1, ord($bytes[11]));      // Quantity lo
    }

    public function testBuildWriteSingleRegisterRequest(): void
    {
        $request = ModbusRequest::writeSingleRegister(1, 0, 0x1234);
        $bytes = $request->toBytes();

        $this->assertSame(0x06, ord($bytes[7]));
        $this->assertSame(0x12, ord($bytes[10]));
        $this->assertSame(0x34, ord($bytes[11]));
    }

    public function testParseReadHoldingRegistersResponse(): void
    {
        $rawBytes = hex2bin('00010000000501030242C8');
        $response = ModbusResponse::fromBytes($rawBytes);

        $this->assertSame(1, $response->getTransactionId());
        $this->assertSame(1, $response->getUnitId());
        $this->assertSame([0x42, 0xC8], $response->getData()['bytes']);
    }

    public function testFromBytesDetectsExceptionResponse(): void
    {
        $rawBytes = hex2bin('000100000003018302');
        $this->expectException(ModbusException::class);
        ModbusResponse::fromBytes($rawBytes);
    }

    public function testCrc16Calculation(): void
    {
        $data = hex2bin('010300000001');
        $crc = ModbusFrame::crc16($data);
        $this->assertSame(0x840A, $crc);
    }

    public function testCrcValidation(): void
    {
        // CRC-16/Modbus = 0x840A, transmitted low byte first: 0x0A then 0x84
        $validFrame = hex2bin('0103000000010A84');
        $this->assertTrue(ModbusFrame::validateCrc($validFrame));

        $corruptFrame = hex2bin('0103000000010000');
        $this->assertFalse(ModbusFrame::validateCrc($corruptFrame));
    }

    public function testWriteMultipleRegistersRequest(): void
    {
        $request = ModbusRequest::writeMultipleRegisters(1, 0, [0x1111, 0x2222]);
        $bytes = $request->toBytes();

        $this->assertSame(0x10, ord($bytes[7]));     // FC
        $this->assertSame(0, ord($bytes[8]));         // Start addr
        $this->assertSame(0, ord($bytes[9]));
        $this->assertSame(0, ord($bytes[10]));        // Quantity hi
        $this->assertSame(2, ord($bytes[11]));         // Quantity lo = 2
        $this->assertSame(4, ord($bytes[12]));         // Byte count = 4
    }

    public function testGetRegistersParsesMultipleValues(): void
    {
        // Response with 2 registers: values 42 (0x002A) and 100 (0x0064)
        $rawBytes = hex2bin('000100000007010304002A0064');
        $response = ModbusResponse::fromBytes($rawBytes);

        $registers = $response->getRegisters();
        $this->assertSame([42, 100], $registers);
    }
}
