<?php

namespace Erikwang2013\IndustrialProtocols\EtherNetIP\Frame;

use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class EtherNetIPFrame implements FrameInterface
{
    private function __construct(
        private int $command,
        private int $sessionHandle = 0,
        private string $data = '',
        private int $context = 0,
    ) {}

    public static function registerSession(): self
    {
        // Register Session: command=0x0065, length=4, protocol=1, flags=0
        $data = pack('v', 1) . pack('v', 0);
        return new self(0x0065, 0, $data);
    }

    public static function unregisterSession(int $sessionHandle): self
    {
        return new self(0x0066, $sessionHandle);
    }

    public static function readTag(int $sessionHandle, string $tagName): self
    {
        // CIP Read Tag Service
        $tagLen = strlen($tagName);
        $cipData = pack('v', 0x004C)  // CIP Read Tag Service
                 . pack('v', 2 + $tagLen) // request path size in words
                 . pack('v', 0x91) . chr($tagLen) . $tagName // symbolic segment
                 . pack('v', 1); // element count
        return new self(0x0070, $sessionHandle, $cipData); // SendRRData
    }

    public function toBytes(): string
    {
        $payload = pack('v', 0) // status
                 . pack('v', 0) // options
                 . $this->data;
        $length = 24 + strlen($payload);
        return pack('v', $this->command)
             . pack('v', $length)
             . pack('V', $this->sessionHandle)
             . pack('V', 0) // status
             . pack('Q', $this->context) // sender context
             . pack('V', 0) // options
             . $payload;
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 24) {
            throw new \RuntimeException('EIP frame too short');
        }
        $command = unpack('v', substr($bytes, 0, 2))[1];
        $sessionHandle = unpack('V', substr($bytes, 4, 4))[1];
        $data = substr($bytes, 24);
        return new self($command, $sessionHandle, $data, 0);
    }

    public function getData(): array
    {
        $result = [
            'command' => $this->command,
            'session_handle' => $this->sessionHandle,
        ];
        if (strlen($this->data) > 0) {
            $result['payload'] = bin2hex($this->data);
        }
        return $result;
    }
}
