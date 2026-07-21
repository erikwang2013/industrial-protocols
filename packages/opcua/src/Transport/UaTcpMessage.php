<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Transport;

class UaTcpMessage
{
    // OPC UA TCP message types (3-byte ASCII)
    public const MSG_HELLO = 'HEL';
    public const MSG_ACK   = 'ACK';
    public const MSG_OPEN  = 'OPN';
    public const MSG_CLOSE = 'CLO';
    public const MSG_MSG   = 'MSG';
    public const MSG_ERR   = 'ERR';

    // Chunk types (single character)
    public const CHUNK_INTERMEDIATE = 'C';
    public const CHUNK_FINAL        = 'F';
    public const CHUNK_ABORT        = 'A';

    public const HEADER_SIZE = 24;

    /**
     * Build OPC UA TCP message header for a chunk.
     *
     * The header is 24 bytes:
     *   [0..2]   MessageType (3 ASCII chars)
     *   [3]      ChunkType (F/C/A)
     *   [4..7]   MessageSize (UInt32, including header)
     *   [8..11]  SecureChannelId (UInt32)
     *   [12..15] SecurityTokenId (UInt32)
     *   [16..19] SequenceNumber (UInt32)
     *   [20..23] RequestId (UInt32)
     */
    public static function buildHeader(
        string $messageType,
        string $chunkType,
        int $messageSize,
        int $secureChannelId = 0,
        int $securityTokenId = 0,
        int $sequenceNumber = 0,
        int $requestId = 0,
    ): string {
        return $messageType
            . chr($chunkType === self::CHUNK_FINAL ? ord('F') : ($chunkType === self::CHUNK_ABORT ? ord('A') : ord('C')))
            . pack('V', $messageSize)
            . pack('V', $secureChannelId)
            . pack('V', $securityTokenId)
            . pack('V', $sequenceNumber)
            . pack('V', $requestId);
    }

    /**
     * Parse OPC UA TCP message header from raw bytes.
     *
     * @return array{messageType:string, chunkType:string, messageSize:int, secureChannelId:int, securityTokenId:int, sequenceNumber:int, requestId:int, headerSize:int}
     */
    public static function parseHeader(string $bytes): array
    {
        if (strlen($bytes) < 24) {
            throw new \RuntimeException('OPC UA TCP header too short: ' . strlen($bytes) . ' bytes');
        }
        return [
            'messageType'      => substr($bytes, 0, 3),
            'chunkType'        => chr(ord($bytes[3])),
            'messageSize'      => unpack('V', substr($bytes, 4, 4))[1],
            'secureChannelId'  => unpack('V', substr($bytes, 8, 4))[1],
            'securityTokenId'  => unpack('V', substr($bytes, 12, 4))[1],
            'sequenceNumber'   => unpack('V', substr($bytes, 16, 4))[1],
            'requestId'        => unpack('V', substr($bytes, 20, 4))[1],
            'headerSize'       => self::HEADER_SIZE,
        ];
    }

    /**
     * Build a Hello message (variable length, min 32 bytes).
     *
     * Hello layout:
     *   [0..2]   "HEL"
     *   [3]      ChunkType = 'F'
     *   [4..7]   MessageSize (UInt32, total including header)
     *   [8..11]  ProtocolVersion (UInt32)
     *   [12..15] ReceiveBufferSize (UInt32)
     *   [16..19] SendBufferSize (UInt32)
     *   [20..23] MaxMessageSize (UInt32)
     *   [24..27] MaxChunkCount (UInt32)
     *   [28..31] EndpointUrl length (UInt32)
     *   [32..]   EndpointUrl (UTF-8)
     */
    public static function buildHello(string $endpointUrl): string
    {
        $payload = pack('V', 0)                              // ProtocolVersion
                 . pack('V', 65536)                          // ReceiveBufferSize
                 . pack('V', 65536)                          // SendBufferSize
                 . pack('V', 0)                              // MaxMessageSize (0 = unlimited)
                 . pack('V', 0)                              // MaxChunkCount (0 = unlimited)
                 . pack('V', strlen($endpointUrl))            // EndpointUrl length
                 . $endpointUrl;                              // EndpointUrl

        $totalSize = self::HEADER_SIZE + strlen($payload);

        return self::MSG_HELLO
            . chr(ord('F'))                                  // ChunkType
            . pack('V', $totalSize)                          // MessageSize
            . $payload;
    }

    /**
     * Parse an Acknowledge message (28 bytes).
     *
     * @return array{protocolVersion:int, receiveBufferSize:int, sendBufferSize:int, maxMessageSize:int, maxChunkCount:int}
     *
     * @throws \RuntimeException if message type is not ACK
     */
    public static function parseAcknowledge(string $bytes): array
    {
        if (substr($bytes, 0, 3) !== self::MSG_ACK) {
            throw new \RuntimeException('Expected ACK, got: ' . substr($bytes, 0, 3));
        }
        if (strlen($bytes) < 28) {
            throw new \RuntimeException('ACK message too short: ' . strlen($bytes) . ' bytes');
        }
        return [
            'protocolVersion'    => unpack('V', substr($bytes, 8, 4))[1],
            'receiveBufferSize'  => unpack('V', substr($bytes, 12, 4))[1],
            'sendBufferSize'     => unpack('V', substr($bytes, 16, 4))[1],
            'maxMessageSize'     => unpack('V', substr($bytes, 20, 4))[1],
            'maxChunkCount'      => unpack('V', substr($bytes, 24, 4))[1],
        ];
    }
}
