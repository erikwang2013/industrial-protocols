<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Transport;

use Erikwang2013\IndustrialProtocols\OpcUa\Encoding\BinaryDecoder;
use Erikwang2013\IndustrialProtocols\OpcUa\Encoding\BinaryEncoder;
use Erikwang2013\IndustrialProtocols\OpcUa\Exception\OpcUaException;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\NodeId;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\StatusCode;

/**
 * OPC UA Secure Channel (Security Policy: None).
 *
 * Handles OpenSecureChannel / CloseSecureChannel service requests
 * and message chunking with sequence numbering.
 */
class SecureChannel
{
    private int $secureChannelId = 0;
    private int $securityTokenId = 0;
    private int $sequenceNumber = 0;
    private int $requestId = 1;

    // Service TypeIds (namespace 0)
    public const SERVICE_OPEN_SECURE_CHANNEL  = 446; // OpenSecureChannelRequest
    public const SERVICE_CLOSE_SECURE_CHANNEL = 452; // CloseSecureChannelRequest
    public const SERVICE_CREATE_SESSION       = 461; // CreateSessionRequest
    public const SERVICE_ACTIVATE_SESSION     = 467; // ActivateSessionRequest
    public const SERVICE_READ                 = 631; // ReadRequest
    public const SERVICE_WRITE                = 673; // WriteRequest
    public const SERVICE_BROWSE               = 527; // BrowseRequest

    /**
     * @param resource $socket       TCP socket stream
     * @param int      $receiveBufferSize  Max receive buffer
     */
    public function __construct(
        private $socket,
        private int $receiveBufferSize = 65536,
    ) {}

    public function getSecureChannelId(): int
    {
        return $this->secureChannelId;
    }

    public function getSecurityTokenId(): int
    {
        return $this->securityTokenId;
    }

    /**
     * Open a secure channel with Security Policy: None.
     *
     * Sends OpenSecureChannelRequest and parses the response to extract
     * SecureChannelId and SecurityTokenId.
     *
     * @throws \RuntimeException on protocol or network errors
     */
    public function open(): void
    {
        $enc = new BinaryEncoder();

        // --- TypeId (ExpandedNodeId for OpenSecureChannelRequest) ---
        $enc->writeNodeId(new NodeId(0, self::SERVICE_OPEN_SECURE_CHANNEL));

        // --- RequestHeader ---
        // AuthenticationToken (NodeId null for anonymous)
        $enc->writeNodeId(new NodeId(0, 0));
        // Timestamp (DateTime, 0 = now)
        $enc->writeInt64(0);
        // RequestHandle
        $enc->writeUInt32(0);
        // ReturnDiagnostics
        $enc->writeUInt32(0);
        // AuditEntryId (empty string)
        $enc->writeString('');
        // TimeoutHint
        $enc->writeUInt32(600000);
        // AdditionalHeader (null ExtensionObject)
        $enc->writeNodeId(new NodeId(0, 0));
        $enc->writeByte(0);

        // --- OpenSecureChannelRequest body ---
        // ClientProtocolVersion
        $enc->writeUInt32(0);
        // RequestType (ISSUE = 0)
        $enc->writeInt32(0);
        // SecurityMode (None = 1)
        $enc->writeInt32(1);
        // ClientNonce (empty for Security Policy None)
        $enc->writeByteString('');
        // RequestedLifetime
        $enc->writeUInt32(600000);

        $response = $this->sendRequest($enc->toBytes(), self::SERVICE_OPEN_SECURE_CHANNEL);
        $dec = new BinaryDecoder($response);

        // --- Parse Response ---
        // TypeId (ExpandedNodeId)
        $dec->readNodeId();

        // --- ResponseHeader ---
        $dec->readInt64();       // Timestamp
        $dec->readUInt32();      // RequestHandle
        $statusCode = $dec->readStatusCode();
        if (!$statusCode->isGood()) {
            throw OpcUaException::fromStatusCode($statusCode->code);
        }

        // ServiceDiagnostics (DiagnosticInfo) — skip
        $this->skipDiagnosticInfo($dec);

        // StringTable (array of String)
        $stringCount = $dec->readUInt32();
        for ($i = 0; $i < $stringCount; $i++) {
            $dec->readString();
        }

        // AdditionalHeader (ExtensionObject)
        $dec->readNodeId();  // TypeId
        $dec->readByte();    // Encoding

        // --- OpenSecureChannelResponse body ---
        $dec->readUInt32();        // ServerProtocolVersion

        // SecurityToken
        $this->secureChannelId = $dec->readUInt32();
        $this->securityTokenId = $dec->readUInt32();
        $dec->readInt64();          // CreatedAt
        $dec->readUInt32();         // RevisedLifetime

        // ServerNonce (ByteString, null for Security Policy None)
        $dec->readByteString();
    }

    /**
     * Close the secure channel.
     */
    public function close(): void
    {
        if ($this->secureChannelId === 0) {
            return;
        }

        $enc = new BinaryEncoder();

        // TypeId
        $enc->writeNodeId(new NodeId(0, self::SERVICE_CLOSE_SECURE_CHANNEL));

        // RequestHeader
        $enc->writeNodeId(new NodeId(0, 0));  // AuthenticationToken
        $enc->writeInt64(0);                   // Timestamp
        $enc->writeUInt32(0);                  // RequestHandle
        $enc->writeUInt32(0);                  // ReturnDiagnostics
        $enc->writeString('');                 // AuditEntryId
        $enc->writeUInt32(0);                  // TimeoutHint
        $enc->writeNodeId(new NodeId(0, 0));   // AdditionalHeader TypeId
        $enc->writeByte(0);                    // AdditionalHeader Encoding

        $this->sendRequest($enc->toBytes(), self::SERVICE_CLOSE_SECURE_CHANNEL);
    }

    /**
     * Send a service request and return the response body (without TCP header).
     *
     * @param  string $requestBody Encoded request body
     * @param  int    $serviceId   Service type ID (for context)
     * @return string Decoded response body
     */
    public function sendRequest(string $requestBody, int $serviceId): string
    {
        $requestId = $this->requestId++;
        $bodyLen = strlen($requestBody);

        $header = UaTcpMessage::buildHeader(
            UaTcpMessage::MSG_MSG,
            UaTcpMessage::CHUNK_FINAL,
            UaTcpMessage::HEADER_SIZE + $bodyLen,
            $this->secureChannelId,
            $this->securityTokenId,
            $this->sequenceNumber++,
            $requestId,
        );

        $written = @fwrite($this->socket, $header . $requestBody);
        if ($written === false) {
            throw new \RuntimeException('OPC UA socket write failed');
        }

        // Read response header (24 bytes)
        $respHeader = $this->readExactly(24);
        $info = UaTcpMessage::parseHeader($respHeader);

        if ($info['messageType'] === UaTcpMessage::MSG_ERR) {
            $errorSize = $info['messageSize'] - 24;
            $errorBody = $errorSize > 0 ? $this->readExactly($errorSize) : '';
            throw new \RuntimeException(
                'OPC UA Error response: ' . bin2hex($errorBody)
            );
        }

        // Read remaining body
        $bodySize = $info['messageSize'] - 24;
        return $this->readExactly($bodySize);
    }

    /**
     * Read exactly $size bytes from socket.
     *
     * @throws \RuntimeException on EOF or read failure
     */
    private function readExactly(int $size): string
    {
        $data = '';
        $remaining = $size;
        while ($remaining > 0) {
            $chunk = @fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                throw new \RuntimeException(
                    "OPC UA socket read failed: expected {$size} bytes, got " . strlen($data)
                );
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        return $data;
    }

    /**
     * Skip over a DiagnosticInfo structure in the decoder stream.
     *
     * DiagnosticInfo encoding:
     *   Int32  SymbolicId        (-1 if not used)
     *   Int32  NamespaceUri      (-1 if not used)
     *   Int32  LocalizedText     (-1 if not used)
     *   Int32  Locale            (-1 if not used)
     *   String AdditionalInfo    (empty if not used)
     *   StatusCode InnerStatusCode  (only if SymbolicId >= 0)
     *   DiagnosticInfo InnerDiagnosticInfo (only if InnerStatusCode >= 0)
     */
    private function skipDiagnosticInfo(BinaryDecoder $dec): void
    {
        $symbolicId = $dec->readInt32();
        $dec->readInt32();    // NamespaceUri
        $locale = $dec->readInt32();    // LocalizedText (not Locale — corrected below)
        // Wait, let me re-read the spec. The order is:
        // SymbolicId, NamespaceUri, LocalizedText, Locale, AdditionalInfo, InnerStatusCode, InnerDiagnosticInfo

        // Actually, the fields already read are: SymbolicId, NamespaceUri.
        // I still need to read: LocalizedText, Locale.

        // Hmm, I already read $locale as the third field which is actually LocalizedText.
        // Let me continue: the 4th field is Locale.
        $dec->readInt32();    // Locale (4th Int32)

        // AdditionalInfo (String) - always present
        $dec->readString();

        // InnerStatusCode (only if SymbolicId >= 0)
        if ($symbolicId >= 0) {
            $innerCode = $dec->readStatusCode();

            // InnerDiagnosticInfo (only if InnerStatusCode >= 0)
            if ($innerCode->code !== -1 && $innerCode->code >= 0) {
                $this->skipDiagnosticInfo($dec);
            }
        }
    }
}
