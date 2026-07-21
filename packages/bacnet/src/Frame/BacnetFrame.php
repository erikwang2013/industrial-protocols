<?php

namespace IndustrialProtocols\Bacnet\Frame;

use IndustrialProtocols\Protocol\FrameInterface;

class BacnetFrame implements FrameInterface
{
    private function __construct(
        private int $bvllType,    // 0x81 = BACnet/IP
        private int $bvllFunction,// 0x0A = Original-Unicast, 0x0B = Original-Broadcast
        private string $npdu,
    ) {}

    public static function whoIs(): self
    {
        // Who-Is: UnconfirmedRequest PDU, service choice = Who-Is (no params = all devices)
        $npdu = chr(0x01)  // Version
              . chr(0x10)  // PDU type: UnconfirmedRequest
              . chr(0x00) . chr(0x00) // Max APDU
              . chr(0x08); // Service choice: Who-Is (8)
        return new self(0x81, 0x0B, $npdu);
    }

    public static function readProperty(int $deviceId, int $objectType, int $objectInstance, int $propertyId): self
    {
        // ConfirmedRequest PDU with ReadProperty service
        $npdu = chr(0x01)  // Version
              . chr(0x00)  // PDU type: ConfirmedRequest
              . chr(0x00) . chr(0x00) // Max APDU
              . chr(0x01)  // Invoke ID
              . chr(0x0C); // Service choice: ReadProperty (12)

        // Object identifier (context tag 0): [10 bits type | 22 bits instance]
        $objectId = ($objectType << 22) | ($objectInstance & 0x3FFFFF);
        $npdu .= chr(0x0C) . pack('N', $objectId);

        // Property identifier (context tag 1)
        $npdu .= chr(0x19) . chr($propertyId);

        return new self(0x81, 0x0A, $npdu);
    }

    public static function iAm(int $deviceId, int $maxApdu, int $segmentation, int $vendorId): self
    {
        // I-Am: UnconfirmedRequest PDU
        $npdu = chr(0x01)
              . chr(0x10)
              . pack('n', $maxApdu)
              . chr(0x00); // Service choice: I-Am (0)

        // Device object identifier
        $objectId = (8 << 22) | ($deviceId & 0x3FFFFF);
        $npdu .= chr(0xC4) . pack('N', $objectId); // context tag 2, opening
        $npdu .= chr(0x21) . pack('n', $maxApdu);   // max APDU
        $npdu .= chr(0x91) . chr($segmentation);     // segmentation
        $npdu .= chr(0x91) . pack('n', $vendorId);   // vendor ID

        return new self(0x81, 0x0A, $npdu);
    }

    public function toBytes(): string
    {
        $bvll = chr($this->bvllType) . chr($this->bvllFunction)
              . pack('n', strlen($this->npdu) + 4);
        return $bvll . $this->npdu;
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 4) {
            throw new \RuntimeException('BACnet frame too short');
        }
        $bvllType = ord($bytes[0]);
        $bvllFunc = ord($bytes[1]);
        $length = unpack('n', substr($bytes, 2, 2))[1];
        $npdu = substr($bytes, 4, $length - 4);
        return new self($bvllType, $bvllFunc, $npdu);
    }

    public function getData(): array
    {
        return [
            'bvll_type' => $this->bvllType,
            'bvll_function' => $this->bvllFunction,
            'npdu_length' => strlen($this->npdu),
        ];
    }
}
