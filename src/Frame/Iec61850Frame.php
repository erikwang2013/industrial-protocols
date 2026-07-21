<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Iec61850\Frame;

use Erikwang2013\IndustrialProtocols\Exception\FrameException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * IEC 61850 MMS (Manufacturing Message Specification) basic encoder/decoder.
 *
 * Supports: Initiate/Conclude, Read, Write, VariableAccessSpecification.
 * Data path names: LD/LN.FC.DO.DA (e.g., "IED1/MMXU1.MX.A.phsA")
 */
class Iec61850Frame implements FrameInterface
{
    // MMS PDU types (simplified)
    public const PDU_INITIATE_REQUEST   = 0xA8;
    public const PDU_INITIATE_RESPONSE  = 0xA9;
    public const PDU_CONCLUDE_REQUEST   = 0xAA;
    public const PDU_CONCLUDE_RESPONSE  = 0xAB;
    public const PDU_READ_REQUEST       = 0xAC;
    public const PDU_READ_RESPONSE      = 0xAD;
    public const PDU_WRITE_REQUEST      = 0xAE;
    public const PDU_WRITE_RESPONSE     = 0xAF;

    private int $pduType;
    private int $invokeId;
    private array $data;
    private string $payload;

    public function __construct(
        int $pduType,
        int $invokeId = 1,
        array $data = [],
        string $payload = '',
    ) {
        $this->pduType = $pduType;
        $this->invokeId = $invokeId;
        $this->data = $data;
        $this->payload = $payload;
    }

    public function getPduType(): int { return $this->pduType; }
    public function getPduName(): string { return self::pduName($this->pduType); }
    public function getInvokeId(): int { return $this->invokeId; }
    public function getPayload(): string { return $this->payload; }

    public function getData(): array
    {
        return [
            'pdu_type'  => $this->getPduName(),
            'invoke_id' => $this->invokeId,
            'data'      => $this->data,
            'payload'   => $this->payload,
        ];
    }

    // -- Static factories --

    /**
     * Initiate MMS session request.
     */
    public static function initiateRequest(
        int $proposedMaxPduSize = 65000,
        int $proposedMaxServOutstanding = 5,
        array $servicesSupported = [],
    ): self {
        return new self(self::PDU_INITIATE_REQUEST, 1, [
            'proposed_max_pdu_size'         => $proposedMaxPduSize,
            'proposed_max_serv_outstanding'  => $proposedMaxServOutstanding,
            'services_supported'            => $servicesSupported,
        ]);
    }

    /**
     * Conclude MMS session.
     */
    public static function conclude(): self
    {
        return new self(self::PDU_CONCLUDE_REQUEST, 1);
    }

    /**
     * Read request using an IEC 61850 data path.
     *
     * Path format: "IED1/MMXU1.MX.A.phsA"
     * LD = Logical Device, LN = Logical Node, FC.DO.DA = Functional Constraint.Data Object.Data Attribute
     */
    public static function readRequest(string $dataPath, int $invokeId = 1): self
    {
        return new self(self::PDU_READ_REQUEST, $invokeId, [
            'data_path' => $dataPath,
            'variable_access' => self::parseDataPath($dataPath),
        ]);
    }

    /**
     * Write request.
     */
    public static function writeRequest(string $dataPath, mixed $value, int $invokeId = 1): self
    {
        return new self(self::PDU_WRITE_REQUEST, $invokeId, [
            'data_path' => $dataPath,
            'value'     => $value,
            'variable_access' => self::parseDataPath($dataPath),
        ]);
    }

    // -- Encode / Decode --

    public function toBytes(): string
    {
        // Basic ASN.1 BER encoding for MMS PDUs over TPKT
        $mmsBody = $this->encodeMms();

        // TPKT header (RFC 1006): version(1) + reserved(1) + length(2) = 4 bytes
        $tpktLen = strlen($mmsBody) + 4;
        $tpkt = chr(3) . chr(0) . pack('n', $tpktLen);

        return $tpkt . $mmsBody;
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 4) {
            throw new FrameException('IEC 61850 frame too short (min 4 bytes for TPKT)');
        }

        $version = ord($bytes[0]);
        if ($version !== 3) {
            throw new FrameException('Invalid TPKT version: ' . $version);
        }

        $tpktLen = unpack('n', substr($bytes, 2, 2))[1];
        $mmsBody = substr($bytes, 4, $tpktLen - 4);

        return self::decodeMms($mmsBody);
    }

    // -- Helpers --

    public static function pduName(int $type): string
    {
        return match ($type) {
            self::PDU_INITIATE_REQUEST  => 'INITIATE_REQUEST',
            self::PDU_INITIATE_RESPONSE => 'INITIATE_RESPONSE',
            self::PDU_CONCLUDE_REQUEST  => 'CONCLUDE_REQUEST',
            self::PDU_CONCLUDE_RESPONSE => 'CONCLUDE_RESPONSE',
            self::PDU_READ_REQUEST      => 'READ_REQUEST',
            self::PDU_READ_RESPONSE     => 'READ_RESPONSE',
            self::PDU_WRITE_REQUEST     => 'WRITE_REQUEST',
            self::PDU_WRITE_RESPONSE    => 'WRITE_RESPONSE',
            default                     => 'UNKNOWN',
        };
    }

    /**
     * Parse IEC 61850 data path into components.
     * "IED1/MMXU1.MX.A.phsA" =>
     *   ['ld' => 'IED1', 'ln' => 'MMXU1', 'fc' => 'MX', 'do' => 'A', 'da' => 'phsA']
     */
    public static function parseDataPath(string $path): array
    {
        $result = ['ld' => '', 'ln' => '', 'fc' => '', 'do' => '', 'da' => ''];

        // Split LD from LN
        $parts = explode('/', $path, 2);
        $result['ld'] = $parts[0];
        $rest = $parts[1] ?? '';

        // Split LN from FC.DO.DA
        $lnParts = explode('.', $rest, 2);
        $result['ln'] = $lnParts[0];
        $remainder = $lnParts[1] ?? '';

        // Split FC, DO, DA
        $subParts = explode('.', $remainder);
        $result['fc'] = $subParts[0] ?? '';
        $result['do'] = $subParts[1] ?? '';
        $result['da'] = $subParts[2] ?? '';

        return $result;
    }

    /**
     * Build a data path from components.
     */
    public static function buildDataPath(array $components): string
    {
        $path = ($components['ld'] ?? '') . '/' . ($components['ln'] ?? '');
        $suffix = implode('.', array_filter([
            $components['fc'] ?? '',
            $components['do'] ?? '',
            $components['da'] ?? '',
        ]));
        if ($suffix !== '') {
            $path .= '.' . $suffix;
        }
        return $path;
    }

    private function encodeMms(): string
    {
        // Simplified ASN.1 BER encoding
        $body = chr($this->pduType);

        // Encode invokeId as INTEGER
        $invokeBytes = $this->encodeInteger($this->invokeId);

        // Encode data as SEQUENCE (simplified)
        $dataBytes = '';
        if (isset($this->data['data_path'])) {
            $pathStr = $this->data['data_path'];
            $dataBytes .= chr(0x0C) . chr(strlen($pathStr)) . $pathStr; // UTF8String
        }

        $contentLen = strlen($invokeBytes) + strlen($dataBytes) + strlen($this->payload);

        // Length encoding
        if ($contentLen < 128) {
            $body .= chr($contentLen);
        } else {
            $lenBytes = $this->encodeLengthBytes($contentLen);
            $body .= chr(0x80 | strlen($lenBytes)) . $lenBytes;
        }

        $body .= $invokeBytes . $dataBytes . $this->payload;
        return $body;
    }

    private function encodeInteger(int $value): string
    {
        if ($value === 0) return "\x02\x01\x00";
        $bytes = '';
        $v = $value;
        while ($v > 0) {
            $bytes = chr($v & 0xFF) . $bytes;
            $v >>= 8;
        }
        return chr(0x02) . chr(strlen($bytes)) . $bytes;
    }

    private function encodeLengthBytes(int $len): string
    {
        $bytes = '';
        $v = $len;
        while ($v > 0) {
            $bytes = chr($v & 0xFF) . $bytes;
            $v >>= 8;
        }
        return $bytes === '' ? "\x00" : $bytes;
    }

    private static function decodeMms(string $body): static
    {
        if (strlen($body) < 1) {
            throw new FrameException('MMS body too short');
        }

        $pduType = ord($body[0]);
        $offset = 1;

        // Decode length
        $len = ord($body[$offset]);
        $offset++;
        if ($len & 0x80) {
            $numLenBytes = $len & 0x7F;
            $len = 0;
            for ($i = 0; $i < $numLenBytes; $i++) {
                $len = ($len << 8) | ord($body[$offset + $i]);
            }
            $offset += $numLenBytes;
        }

        // Decode invokeId (INTEGER tag = 0x02)
        $invokeId = 0;
        if ($offset < strlen($body) && ord($body[$offset]) === 0x02) {
            $offset++;
            $intLen = ord($body[$offset]);
            $offset++;
            for ($i = 0; $i < $intLen; $i++) {
                $invokeId = ($invokeId << 8) | ord($body[$offset + $i]);
            }
            $offset += $intLen;
        }

        $data = [];
        $payload = substr($body, $offset);

        return new self($pduType, $invokeId, $data, $payload);
    }
}
