<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Mqtt\Frame;

use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * MQTT 3.1.1 packet encoder/decoder.
 *
 * Supports: CONNECT, CONNACK, PUBLISH, PUBACK, PUBREC, PUBREL, PUBCOMP,
 * SUBSCRIBE, SUBACK, UNSUBSCRIBE, UNSUBACK, PINGREQ, PINGRESP, DISCONNECT.
 */
class MqttFrame implements FrameInterface
{
    // Packet types
    public const TYPE_CONNECT     = 0x10;
    public const TYPE_CONNACK     = 0x20;
    public const TYPE_PUBLISH     = 0x30;
    public const TYPE_PUBACK      = 0x40;
    public const TYPE_PUBREC      = 0x50;
    public const TYPE_PUBREL      = 0x62;
    public const TYPE_PUBCOMP     = 0x70;
    public const TYPE_SUBSCRIBE   = 0x82;
    public const TYPE_SUBACK      = 0x90;
    public const TYPE_UNSUBSCRIBE = 0xA2;
    public const TYPE_UNSUBACK    = 0xB0;
    public const TYPE_PINGREQ     = 0xC0;
    public const TYPE_PINGRESP    = 0xD0;
    public const TYPE_DISCONNECT  = 0xE0;

    // QoS levels
    public const QOS_0 = 0;
    public const QOS_1 = 1;
    public const QOS_2 = 2;

    private int $type;
    private int $flags;
    private array $variableHeader;
    private string $payload;
    private int $packetId = 0;
    private static int $nextPacketId = 1;

    public function __construct(
        int $type = 0,
        array $variableHeader = [],
        string $payload = '',
        int $flags = 0,
    ) {
        $this->type = $type;
        $this->variableHeader = $variableHeader;
        $this->payload = $payload;
        $this->flags = $flags;
    }

    public function getType(): int { return $this->type; }
    public function getTypeName(): string { return self::typeName($this->type); }
    public function getPayload(): string { return $this->payload; }
    public function getVariableHeader(): array { return $this->variableHeader; }
    public function getPacketId(): int { return $this->variableHeader['packet_id'] ?? $this->packetId; }

    public function getData(): array
    {
        return [
            'type'           => $this->getTypeName(),
            'type_byte'      => $this->type,
            'variable_header' => $this->variableHeader,
            'payload'        => $this->payload,
            'packet_id'      => $this->packetId,
        ];
    }

    // -- Static factories --

    public static function connect(
        string $clientId,
        ?string $username = null,
        ?string $password = null,
        int $keepAlive = 60,
        bool $cleanSession = true,
    ): self {
        $vh = [
            'protocol_name'  => 'MQTT',
            'protocol_level' => 4,
            'connect_flags'  => 0,
            'keep_alive'     => $keepAlive,
            'client_id'      => $clientId,
        ];

        $flags = $cleanSession ? 0x02 : 0x00;
        if ($username !== null) {
            $flags |= 0x80;
            $vh['username'] = $username;
        }
        if ($password !== null) {
            $flags |= 0x40;
            $vh['password'] = $password;
        }
        $vh['connect_flags'] = $flags;

        return new self(self::TYPE_CONNECT, $vh);
    }

    public static function publish(string $topic, string $payload, int $qos = 0, bool $retain = false, int $packetId = 0): self
    {
        $flags = 0;
        if ($retain) $flags |= 0x01;
        $flags |= ($qos & 0x03) << 1;

        $type = self::TYPE_PUBLISH | $flags;

        $vh = ['topic' => $topic];
        if ($qos > 0) {
            $vh['packet_id'] = $packetId ?: self::nextPacketId();
        }

        return new self($type, $vh, $payload, $flags);
    }

    public static function pubAck(int $packetId): self
    {
        return new self(self::TYPE_PUBACK, ['packet_id' => $packetId]);
    }

    public static function pubRec(int $packetId): self
    {
        return new self(self::TYPE_PUBREC, ['packet_id' => $packetId]);
    }

    public static function pubRel(int $packetId): self
    {
        return new self(self::TYPE_PUBREL, ['packet_id' => $packetId]);
    }

    public static function pubComp(int $packetId): self
    {
        return new self(self::TYPE_PUBCOMP, ['packet_id' => $packetId]);
    }

    /**
     * @param array<string,int> $topics topic => qos
     */
    public static function subscribe(array $topics): self
    {
        $packetId = self::nextPacketId();
        return new self(self::TYPE_SUBSCRIBE, ['packet_id' => $packetId, 'topics' => $topics]);
    }

    /**
     * @param string[] $topics
     */
    public static function unsubscribe(array $topics): self
    {
        $packetId = self::nextPacketId();
        return new self(self::TYPE_UNSUBSCRIBE, ['packet_id' => $packetId, 'topics' => $topics]);
    }

    public static function pingReq(): self
    {
        return new self(self::TYPE_PINGREQ);
    }

    public static function pingResp(): self
    {
        return new self(self::TYPE_PINGRESP);
    }

    public static function disconnect(): self
    {
        return new self(self::TYPE_DISCONNECT);
    }

    // -- Encode / Decode --

    public function toBytes(): string
    {
        $fixedHeader = chr($this->type);

        $body = match ($this->type & 0xF0) {
            0x10 => $this->encodeConnect(),
            0x30 => $this->encodePublish(),
            0x40, 0x50, 0x60, 0x70 => $this->encodePacketId(),
            0x80 => $this->encodeSubscribe(),
            0xA0 => $this->encodeUnsubscribe(),
            0xC0, 0xD0, 0xE0 => '',
            default => '',
        };

        $remainingLength = self::encodeRemainingLength(strlen($body));
        return $fixedHeader . $remainingLength . $body;
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 2) {
            throw new \Erikwang2013\IndustrialProtocols\Exception\FrameException('MQTT frame too short');
        }

        $type = ord($bytes[0]);
        $flags = $type & 0x0F;
        $typeByte = $type; // keep original byte including flags

        $pos = 1;
        $rl = self::decodeRemainingLength($bytes, $pos);
        $body = substr($bytes, $pos, $rl);

        return match ($typeByte & 0xF0) {
            0x20 => self::decodeConnAck($typeByte, $body),
            0x30 => self::decodePublish($typeByte, $body, $flags),
            0x40, 0x50, 0x60, 0x70 => self::decodePacketIdResponse($typeByte, $body),
            0x90 => self::decodeSubAck($typeByte, $body),
            0xB0 => self::decodeUnsubAck($typeByte, $body),
            default => new self($typeByte, [], $body, $flags),
        };
    }

    // -- Remaining length encoding --

    public static function encodeRemainingLength(int $length): string
    {
        $encoded = '';
        do {
            $digit = $length % 128;
            $length = intdiv($length, 128);
            if ($length > 0) $digit |= 0x80;
            $encoded .= chr($digit);
        } while ($length > 0);
        return $encoded;
    }

    public static function decodeRemainingLength(string $bytes, int &$offset): int
    {
        $multiplier = 1;
        $value = 0;
        do {
            if ($offset >= strlen($bytes)) {
                throw new \Erikwang2013\IndustrialProtocols\Exception\FrameException('Incomplete remaining length in MQTT frame');
            }
            $digit = ord($bytes[$offset]);
            $value += ($digit & 0x7F) * $multiplier;
            $multiplier *= 128;
            $offset++;
        } while (($digit & 0x80) !== 0);
        return $value;
    }

    // -- Internal encoders --

    private function encodeConnect(): string
    {
        $body = '';
        $body .= self::encodeString($this->variableHeader['protocol_name']);
        $body .= chr($this->variableHeader['protocol_level']);
        $body .= chr($this->variableHeader['connect_flags']);
        $body .= pack('n', $this->variableHeader['keep_alive']);
        $body .= self::encodeString($this->variableHeader['client_id']);

        if (isset($this->variableHeader['username'])) {
            $body .= self::encodeString($this->variableHeader['username']);
        }
        if (isset($this->variableHeader['password'])) {
            $body .= self::encodeString($this->variableHeader['password']);
        }
        return $body;
    }

    private function encodePublish(): string
    {
        $body = '';
        $body .= self::encodeString($this->variableHeader['topic']);
        if ($this->getQos() > 0) {
            $body .= pack('n', $this->variableHeader['packet_id']);
        }
        $body .= $this->payload;
        return $body;
    }

    private function encodePacketId(): string
    {
        return pack('n', $this->variableHeader['packet_id']);
    }

    private function encodeSubscribe(): string
    {
        $body = pack('n', $this->variableHeader['packet_id']);
        foreach ($this->variableHeader['topics'] as $topic => $qos) {
            $body .= self::encodeString($topic);
            $body .= chr($qos & 0x03);
        }
        return $body;
    }

    private function encodeUnsubscribe(): string
    {
        $body = pack('n', $this->variableHeader['packet_id']);
        foreach ($this->variableHeader['topics'] as $topic) {
            $body .= self::encodeString($topic);
        }
        return $body;
    }

    // -- Internal decoders --

    private static function decodeConnAck(int $type, string $body): self
    {
        $ackFlags = ord($body[0] ?? "\x00");
        $returnCode = ord($body[1] ?? "\x00");
        return new self($type, [
            'session_present' => ($ackFlags & 0x01) !== 0,
            'return_code'     => $returnCode,
        ]);
    }

    private static function decodePublish(int $type, string $body, int $flags): self
    {
        $qos = ($flags >> 1) & 0x03;
        $pos = 0;
        $topic = self::decodeString($body, $pos);
        $packetId = 0;
        if ($qos > 0) {
            $packetId = unpack('n', substr($body, $pos, 2))[1];
            $pos += 2;
        }
        $payload = substr($body, $pos);
        return new self($type, ['topic' => $topic, 'packet_id' => $packetId], $payload, $flags);
    }

    private static function decodePacketIdResponse(int $type, string $body): self
    {
        $packetId = unpack('n', $body)[1];
        return new self($type, ['packet_id' => $packetId]);
    }

    private static function decodeSubAck(int $type, string $body): self
    {
        $packetId = unpack('n', $body)[1];
        $granted = [];
        for ($i = 2; $i < strlen($body); $i++) {
            $granted[] = ord($body[$i]);
        }
        return new self($type, ['packet_id' => $packetId, 'granted_qos' => $granted]);
    }

    private static function decodeUnsubAck(int $type, string $body): self
    {
        $packetId = unpack('n', $body)[1];
        return new self($type, ['packet_id' => $packetId]);
    }

    // -- Helpers --

    public function getQos(): int
    {
        return ($this->flags >> 1) & 0x03;
    }

    public function isRetain(): bool
    {
        return ($this->flags & 0x01) !== 0;
    }

    public function isPublish(): bool
    {
        return ($this->type & 0xF0) === 0x30;
    }

    public function isConnAck(): bool
    {
        return $this->type === self::TYPE_CONNACK;
    }

    public function getReturnCode(): int
    {
        return $this->variableHeader['return_code'] ?? -1;
    }

    public static function typeName(int $type): string
    {
        return match ($type & 0xF0) {
            0x10 => 'CONNECT',
            0x20 => 'CONNACK',
            0x30 => 'PUBLISH',
            0x40 => 'PUBACK',
            0x50 => 'PUBREC',
            0x60 => 'PUBREL',
            0x70 => 'PUBCOMP',
            0x80 => 'SUBSCRIBE',
            0x90 => 'SUBACK',
            0xA0 => 'UNSUBSCRIBE',
            0xB0 => 'UNSUBACK',
            0xC0 => 'PINGREQ',
            0xD0 => 'PINGRESP',
            0xE0 => 'DISCONNECT',
            default => 'UNKNOWN',
        };
    }

    private static function encodeString(string $str): string
    {
        return pack('n', strlen($str)) . $str;
    }

    private static function decodeString(string $data, int &$offset): string
    {
        $len = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        $str = substr($data, $offset, $len);
        $offset += $len;
        return $str;
    }

    private static function nextPacketId(): int
    {
        $id = self::$nextPacketId;
        self::$nextPacketId = ($id % 65535) + 1;
        return $id;
    }
}
