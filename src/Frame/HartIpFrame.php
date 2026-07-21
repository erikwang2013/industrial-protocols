<?php

namespace Erikwang2013\IndustrialProtocols\HartIp\Frame;

use Erikwang2013\IndustrialProtocols\HartIp\Exception\HartIpException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * HART-IP frame over TCP/UDP (port 5094).
 *
 * HART-IP header (8 bytes):
 *   Version     (1 byte)  = 0x01
 *   MessageType (1 byte)  = 0x00 (request), 0x01 (response), 0x02 (publish), 0x03 (error)
 *   Status      (1 byte)  = 0x00 for request, error code for error type
 *   Sequence    (2 bytes) = message sequence number (big-endian)
 *   MessageID   (2 bytes) = unique message identifier (big-endian)
 *   PayloadLen  (1 byte)  = length of HART command payload
 *
 * Then follows a standard HART command (preamble + delimiter + address + command + dataLen + data + checksum).
 */
class HartIpFrame implements FrameInterface
{
    public const VERSION = 1;

    /** Message types */
    public const TYPE_REQUEST  = 0x00;
    public const TYPE_RESPONSE = 0x01;
    public const TYPE_PUBLISH  = 0x02;
    public const TYPE_ERROR    = 0x03;

    /** Error status codes */
    public const STATUS_OK             = 0x00;
    public const STATUS_DEVICE_ERROR   = 0x40;
    public const STATUS_COMMAND_ERROR  = 0x80;

    private int $version;
    private int $messageType;
    private int $status;
    private int $sequenceNumber;
    private int $messageId;
    private string $hartCommand;

    /**
     * @param int $messageType HART-IP message type
     * @param int $sequenceNumber Monotonically increasing sequence number
     * @param int $messageId Unique message ID
     * @param string $hartCommand Raw HART command payload (preamble + delimiter + ... + checksum)
     * @param int $status Status byte (0x00 for requests)
     */
    public function __construct(
        int $messageType = self::TYPE_REQUEST,
        int $sequenceNumber = 0,
        int $messageId = 0,
        string $hartCommand = '',
        int $status = self::STATUS_OK,
    ) {
        $this->version = self::VERSION;
        $this->messageType = $messageType;
        $this->sequenceNumber = $sequenceNumber & 0xFFFF;
        $this->messageId = $messageId & 0xFFFF;
        $this->hartCommand = $hartCommand;
        $this->status = $status;
    }

    /**
     * Create a HART-IP request frame encapsulating a HART command.
     */
    public static function request(int $seqNum, int $msgId, string $hartCommand): self
    {
        return new self(self::TYPE_REQUEST, $seqNum, $msgId, $hartCommand);
    }

    /**
     * Create a HART-IP response frame.
     */
    public static function response(int $seqNum, int $msgId, string $hartResponse, int $status = self::STATUS_OK): self
    {
        return new self(self::TYPE_RESPONSE, $seqNum, $msgId, $hartResponse, $status);
    }

    /**
     * Pack the HART-IP header (8 bytes) + HART command payload.
     */
    public function toBytes(): string
    {
        $payloadLen = strlen($this->hartCommand);
        $header = pack('CCCCnnC',
            $this->version,
            $this->messageType,
            $this->status,
            0x00,  // reserved
            $this->sequenceNumber,
            $this->messageId,
            $payloadLen,
        );
        return $header . $this->hartCommand;
    }

    /**
     * Parse a HART-IP frame from wire bytes.
     */
    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 9) {
            throw HartIpException::headerTooShort(strlen($bytes));
        }

        $header = unpack('Cversion/Ctype/Cstatus/Creserved/nsequence/nmessageId/Clength', substr($bytes, 0, 9));

        if ($header['version'] !== self::VERSION) {
            throw HartIpException::invalidVersion((int) $header['version']);
        }

        $hartCommand = substr($bytes, 9, (int) $header['length']);

        return new self(
            $header['type'],
            $header['sequence'],
            $header['messageId'],
            $hartCommand,
            $header['status'],
        );
    }

    public function getData(): array
    {
        return [
            'version' => $this->version,
            'message_type' => $this->messageType,
            'status' => $this->status,
            'sequence_number' => $this->sequenceNumber,
            'message_id' => $this->messageId,
            'hart_command' => bin2hex($this->hartCommand),
        ];
    }

    // ---- Accessors ----

    public function getVersion(): int { return $this->version; }
    public function getMessageType(): int { return $this->messageType; }
    public function getStatus(): int { return $this->status; }
    public function getSequenceNumber(): int { return $this->sequenceNumber; }
    public function getMessageId(): int { return $this->messageId; }
    public function getHartCommand(): string { return $this->hartCommand; }

    /**
     * Check if this is an error response.
     */
    public function isError(): bool
    {
        return $this->messageType === self::TYPE_ERROR || $this->status !== self::STATUS_OK;
    }
}
