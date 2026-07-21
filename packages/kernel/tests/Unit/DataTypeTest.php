<?php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Protocol\DataType;
use PHPUnit\Framework\TestCase;

class DataTypeTest extends TestCase
{
    public function testDataTypeHasExpectedValues(): void
    {
        $this->assertInstanceOf(DataType::class, DataType::BOOL);
        $this->assertInstanceOf(DataType::class, DataType::INT16);
        $this->assertInstanceOf(DataType::class, DataType::UINT16);
        $this->assertInstanceOf(DataType::class, DataType::INT32);
        $this->assertInstanceOf(DataType::class, DataType::UINT32);
        $this->assertInstanceOf(DataType::class, DataType::FLOAT32);
        $this->assertInstanceOf(DataType::class, DataType::FLOAT64);
        $this->assertInstanceOf(DataType::class, DataType::STRING);
    }

    public function testDataTypeGetSize(): void
    {
        $this->assertSame(1, DataType::BOOL->getSize());
        $this->assertSame(2, DataType::INT16->getSize());
        $this->assertSame(2, DataType::UINT16->getSize());
        $this->assertSame(4, DataType::INT32->getSize());
        $this->assertSame(4, DataType::UINT32->getSize());
        $this->assertSame(4, DataType::FLOAT32->getSize());
        $this->assertSame(8, DataType::FLOAT64->getSize());
    }
}
