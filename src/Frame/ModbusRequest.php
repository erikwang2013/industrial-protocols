<?php

namespace IndustrialProtocols\Modbus\Frame;

use IndustrialProtocols\Protocol\FrameInterface;

class ModbusRequest extends ModbusFrame implements FrameInterface
{
    private static int $nextTransactionId = 1;

    private function __construct(
        private int $unitId,
        private int $functionCode,
        private string $pdu,
        private int $transactionId,
    ) {}

    public static function readHoldingRegisters(int $unitId, int $startAddr, int $quantity): self
    {
        $pdu = chr(0x03) . pack('n', $startAddr) . pack('n', $quantity);
        return new self($unitId, 0x03, $pdu, self::$nextTransactionId++);
    }

    public static function readInputRegisters(int $unitId, int $startAddr, int $quantity): self
    {
        $pdu = chr(0x04) . pack('n', $startAddr) . pack('n', $quantity);
        return new self($unitId, 0x04, $pdu, self::$nextTransactionId++);
    }

    public static function readCoils(int $unitId, int $startAddr, int $quantity): self
    {
        $pdu = chr(0x01) . pack('n', $startAddr) . pack('n', $quantity);
        return new self($unitId, 0x01, $pdu, self::$nextTransactionId++);
    }

    public static function writeSingleRegister(int $unitId, int $address, int $value): self
    {
        $pdu = chr(0x06) . pack('n', $address) . pack('n', $value);
        return new self($unitId, 0x06, $pdu, self::$nextTransactionId++);
    }

    public static function writeMultipleRegisters(int $unitId, int $startAddr, array $values): self
    {
        $count = count($values);
        $byteCount = $count * 2;
        $pdu = chr(0x10) . pack('n', $startAddr) . pack('n', $count) . chr($byteCount);
        foreach ($values as $val) { $pdu .= pack('n', $val); }
        return new self($unitId, 0x10, $pdu, self::$nextTransactionId++);
    }

    public function toBytes(): string
    {
        $length = strlen($this->pdu) + 1;
        return pack('n', $this->transactionId) . pack('n', 0) . pack('n', $length)
            . chr($this->unitId) . $this->pdu;
    }

    public static function fromBytes(string $bytes): static
    {
        throw new \BadMethodCallException('Cannot build request from bytes');
    }

    public function getData(): array
    {
        return [
            'unit_id' => $this->unitId,
            'function_code' => $this->functionCode,
            'transaction_id' => $this->transactionId,
        ];
    }
}
