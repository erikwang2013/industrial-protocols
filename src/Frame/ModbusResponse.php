<?php

namespace IndustrialProtocols\Modbus\Frame;

use IndustrialProtocols\Modbus\Exception\ModbusException;
use IndustrialProtocols\Protocol\FrameInterface;

class ModbusResponse extends ModbusFrame implements FrameInterface
{
    private int $transactionId;
    private int $unitId;
    private int $functionCode;
    private string $data;

    private function __construct() {}

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 9) {
            throw new ModbusException('Response too short: ' . strlen($bytes) . ' bytes');
        }

        $r = new self();
        $r->transactionId = unpack('n', substr($bytes, 0, 2))[1];
        $length = unpack('n', substr($bytes, 4, 2))[1];
        $r->unitId = ord($bytes[6]);
        $r->functionCode = ord($bytes[7]);

        if ($r->functionCode & 0x80) {
            throw ModbusException::fromErrorCode($r->functionCode & 0x7F, ord($bytes[8]));
        }

        $r->data = substr($bytes, 8, $length - 2);
        return $r;
    }

    public function toBytes(): string
    {
        throw new \BadMethodCallException('Response is parsed from bytes');
    }

    public function getData(): array
    {
        // Strip the leading byte count byte for read-type responses
        $payload = isset($this->data[0]) ? substr($this->data, 1) : '';
        return ['bytes' => array_values(unpack('C*', $payload))];
    }

    public function getRegisters(): array
    {
        $byteCount = ord($this->data[0]);
        $registerBytes = substr($this->data, 1, $byteCount);
        $registers = [];
        for ($i = 0; $i < $byteCount / 2; $i++) {
            $registers[] = unpack('n', substr($registerBytes, $i * 2, 2))[1];
        }
        return $registers;
    }

    public function getTransactionId(): int { return $this->transactionId; }
    public function getUnitId(): int { return $this->unitId; }
    public function getFunctionCode(): int { return $this->functionCode; }
}
