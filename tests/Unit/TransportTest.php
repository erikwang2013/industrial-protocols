<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Tests\Unit;

use Erikwang2013\IndustrialProtocols\OpcUa\Transport\UaTcpMessage;
use PHPUnit\Framework\TestCase;

class TransportTest extends TestCase
{
    public function testBuildHello(): void
    {
        $hello = UaTcpMessage::buildHello('opc.tcp://localhost:4840');
        $this->assertSame('HEL', substr($hello, 0, 3));
        $this->assertGreaterThan(32, strlen($hello));
        $this->assertStringContainsString('opc.tcp://localhost:4840', $hello);
    }

    public function testParseAcknowledge(): void
    {
        // Build a valid ACK message (28 bytes):
        // ACK(3) + ChunkType F(1) + MessageSize(4) + ProtocolVersion(4) +
        // RecvBuf(4) + SendBuf(4) + MaxMsg(4) + MaxChunk(4)
        $ack = 'ACK' . chr(ord('F'))
             . pack('V', 28)     // MessageSize (total = 28)
             . pack('V', 0)      // ProtocolVersion
             . pack('V', 65536)  // ReceiveBufferSize
             . pack('V', 65536)  // SendBufferSize
             . pack('V', 0)      // MaxMessageSize
             . pack('V', 0);     // MaxChunkCount

        $this->assertSame(28, strlen($ack));
        $parsed = UaTcpMessage::parseAcknowledge($ack);
        $this->assertSame(0, $parsed['protocolVersion']);
        $this->assertSame(65536, $parsed['receiveBufferSize']);
        $this->assertSame(65536, $parsed['sendBufferSize']);
        $this->assertSame(0, $parsed['maxMessageSize']);
        $this->assertSame(0, $parsed['maxChunkCount']);
    }

    public function testParseAcknowledgeRejectsNonAck(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected ACK');
        UaTcpMessage::parseAcknowledge('ERR' . str_repeat(chr(0), 25));
    }

    public function testParseAcknowledgeRejectsShortMessage(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too short');
        UaTcpMessage::parseAcknowledge('ACK' . chr(0));
    }

    public function testBuildHeader(): void
    {
        $header = UaTcpMessage::buildHeader(
            UaTcpMessage::MSG_MSG,
            UaTcpMessage::CHUNK_FINAL,
            100,
            1,
            2,
            3,
            4,
        );
        $this->assertSame(24, strlen($header));
        $this->assertSame('MSG', substr($header, 0, 3));
        $this->assertSame('F', chr(ord($header[3])));

        $parsed = UaTcpMessage::parseHeader($header);
        $this->assertSame('MSG', $parsed['messageType']);
        $this->assertSame('F', $parsed['chunkType']);
        $this->assertSame(100, $parsed['messageSize']);
        $this->assertSame(1, $parsed['secureChannelId']);
        $this->assertSame(2, $parsed['securityTokenId']);
        $this->assertSame(3, $parsed['sequenceNumber']);
        $this->assertSame(4, $parsed['requestId']);
        $this->assertSame(24, $parsed['headerSize']);
    }

    public function testBuildHeaderChunkTypeIntermediate(): void
    {
        $header = UaTcpMessage::buildHeader(
            UaTcpMessage::MSG_MSG,
            UaTcpMessage::CHUNK_INTERMEDIATE,
            200,
        );
        $this->assertSame('C', chr(ord($header[3])));
    }

    public function testBuildHeaderChunkTypeAbort(): void
    {
        $header = UaTcpMessage::buildHeader(
            UaTcpMessage::MSG_MSG,
            UaTcpMessage::CHUNK_ABORT,
            200,
        );
        $this->assertSame('A', chr(ord($header[3])));
    }

    public function testHelloMessageSize(): void
    {
        $endpoint = 'opc.tcp://127.0.0.1:4840';
        $hello = UaTcpMessage::buildHello($endpoint);
        // HEL(3) + ChunkF(1) + MsgSize(4) +
        // ProtoVer(4) + RecvBuf(4) + SendBuf(4) + MaxMsg(4) + MaxChunk(4) +
        // UrlLen(4) + Url
        $expected = 3 + 1 + 4 + 4 + 4 + 4 + 4 + 4 + 4 + strlen($endpoint);
        $this->assertSame($expected, strlen($hello));
    }

    public function testHelloEndpointUrlLength(): void
    {
        $hello = UaTcpMessage::buildHello('opc.tcp://server:4840');
        // URL length is after 8-byte header + 5*4 protocol fields = offset 28
        $urlLen = unpack('V', substr($hello, 28, 4))[1];
        $this->assertSame(strlen('opc.tcp://server:4840'), $urlLen);
    }

    public function testParseHeaderRejectsShortBytes(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too short');
        UaTcpMessage::parseHeader('MSG');
    }

    public function testMessageTypeConstants(): void
    {
        $this->assertSame('HEL', UaTcpMessage::MSG_HELLO);
        $this->assertSame('ACK', UaTcpMessage::MSG_ACK);
        $this->assertSame('OPN', UaTcpMessage::MSG_OPEN);
        $this->assertSame('CLO', UaTcpMessage::MSG_CLOSE);
        $this->assertSame('MSG', UaTcpMessage::MSG_MSG);
        $this->assertSame('ERR', UaTcpMessage::MSG_ERR);
    }

    public function testChunkTypeConstants(): void
    {
        $this->assertSame('C', UaTcpMessage::CHUNK_INTERMEDIATE);
        $this->assertSame('F', UaTcpMessage::CHUNK_FINAL);
        $this->assertSame('A', UaTcpMessage::CHUNK_ABORT);
    }

    public function testHeaderSizeConstant(): void
    {
        $this->assertSame(24, UaTcpMessage::HEADER_SIZE);
    }
}
