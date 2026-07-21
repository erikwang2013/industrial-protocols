<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Mqtt\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Mqtt\Frame\MqttFrame;
use PHPUnit\Framework\TestCase;

class MqttFrameTest extends TestCase
{
    public function test_remaining_length_encode(): void
    {
        $this->assertSame("\x00", MqttFrame::encodeRemainingLength(0));
        $this->assertSame("\x7F", MqttFrame::encodeRemainingLength(127));
        $this->assertSame("\x80\x01", MqttFrame::encodeRemainingLength(128));
        $this->assertSame("\xFF\x7F", MqttFrame::encodeRemainingLength(16383));
        $this->assertSame("\x80\x80\x01", MqttFrame::encodeRemainingLength(16384));
    }

    public function test_remaining_length_decode(): void
    {
        $offset = 0;
        $this->assertSame(0, MqttFrame::decodeRemainingLength("\x00", $offset));

        $offset = 0;
        $this->assertSame(128, MqttFrame::decodeRemainingLength("\x80\x01", $offset));

        $offset = 0;
        $this->assertSame(16383, MqttFrame::decodeRemainingLength("\xFF\x7F", $offset));
    }

    public function test_connect_frame(): void
    {
        $frame = MqttFrame::connect('test-client', 'user', 'pass', 60);

        $this->assertSame(MqttFrame::TYPE_CONNECT, $frame->getType());
        $this->assertSame('CONNECT', $frame->getTypeName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);
        $this->assertSame(chr(MqttFrame::TYPE_CONNECT), $bytes[0]);

        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertSame(MqttFrame::TYPE_CONNECT, $decoded->getType());
    }

    public function test_publish_frame_qos0(): void
    {
        $frame = MqttFrame::publish('test/topic', 'hello world', 0);

        $this->assertTrue($frame->isPublish());
        $this->assertSame(0, $frame->getQos());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);

        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertTrue($decoded->isPublish());
        $this->assertSame('hello world', $decoded->getPayload());
    }

    public function test_publish_frame_qos1(): void
    {
        $frame = MqttFrame::publish('test/topic', 'qos1 message', 1, false, 42);

        $this->assertTrue($frame->isPublish());
        $this->assertSame(1, $frame->getQos());

        $bytes = $frame->toBytes();

        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertSame(1, $decoded->getQos());
        $this->assertSame('qos1 message', $decoded->getPayload());
        $this->assertSame(42, $decoded->getPacketId());
    }

    public function test_publish_with_retain(): void
    {
        $frame = MqttFrame::publish('retained/topic', 'retained', 0, true);

        $this->assertTrue($frame->isRetain());
        $this->assertTrue($frame->isPublish());

        $bytes = $frame->toBytes();
        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertTrue($decoded->isRetain());
        $this->assertSame('retained', $decoded->getPayload());
    }

    public function test_subscribe_frame(): void
    {
        $topics = ['sensor/+/temperature' => 1, 'sensor/+/humidity' => 0];
        $frame = MqttFrame::subscribe($topics);

        $this->assertSame('SUBSCRIBE', $frame->getTypeName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);
    }

    public function test_unsubscribe_frame(): void
    {
        $frame = MqttFrame::unsubscribe(['test/topic']);

        $this->assertSame('UNSUBSCRIBE', $frame->getTypeName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);
    }

    public function test_pingreq_frame(): void
    {
        $frame = MqttFrame::pingReq();
        $this->assertSame('PINGREQ', $frame->getTypeName());
        $this->assertSame("\xC0\x00", $frame->toBytes());
    }

    public function test_disconnect_frame(): void
    {
        $frame = MqttFrame::disconnect();
        $this->assertSame('DISCONNECT', $frame->getTypeName());
        $this->assertSame("\xE0\x00", $frame->toBytes());
    }

    public function test_connack_decode(): void
    {
        // CONNACK with session present = 0, return code = 0 (accepted)
        $bytes = "\x20\x02\x00\x00";
        $frame = MqttFrame::fromBytes($bytes);

        $this->assertTrue($frame->isConnAck());
        $this->assertSame(0, $frame->getReturnCode());
    }

    public function test_connack_rejected(): void
    {
        // CONNACK with return code 4 (bad username/password)
        $bytes = "\x20\x02\x00\x04";
        $frame = MqttFrame::fromBytes($bytes);

        $this->assertTrue($frame->isConnAck());
        $this->assertSame(4, $frame->getReturnCode());
    }

    public function test_get_data_returns_array(): void
    {
        $frame = MqttFrame::publish('test', 'data');
        $data = $frame->getData();

        $this->assertIsArray($data);
        $this->assertSame('PUBLISH', $data['type']);
        $this->assertSame('data', $data['payload']);
    }

    public function test_puback_roundtrip(): void
    {
        $frame = MqttFrame::pubAck(123);
        $bytes = $frame->toBytes();

        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertSame(123, $decoded->getPacketId());
    }

    public function test_pubrel_roundtrip(): void
    {
        $frame = MqttFrame::pubRel(456);
        $bytes = $frame->toBytes();

        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertSame(456, $decoded->getPacketId());
    }

    public function test_pubrec_roundtrip(): void
    {
        $frame = MqttFrame::pubRec(789);
        $bytes = $frame->toBytes();

        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertSame(789, $decoded->getPacketId());
    }

    public function test_pubcomp_roundtrip(): void
    {
        $frame = MqttFrame::pubComp(999);
        $bytes = $frame->toBytes();

        $decoded = MqttFrame::fromBytes($bytes);
        $this->assertSame(999, $decoded->getPacketId());
    }

    public function test_suback_decode(): void
    {
        // SUBACK: type=0x90, len=3, packetId=1, granted QoS
        $bytes = "\x90\x03\x00\x01\x01";
        $frame = MqttFrame::fromBytes($bytes);

        $this->assertSame('SUBACK', $frame->getTypeName());
        $vh = $frame->getVariableHeader();
        $this->assertSame(1, $vh['packet_id']);
        $this->assertSame([1], $vh['granted_qos']);
    }
}
