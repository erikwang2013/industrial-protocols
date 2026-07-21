<?php

namespace Erikwang2013\IndustrialProtocols\Dnp3\Frame;

use Erikwang2013\IndustrialProtocols\Exception\FrameException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * DNP3 frame encoder/decoder for power utility automation.
 *
 * Frame structure:
 *   Start bytes (0x0564) | Length | Control | Destination | Source | CRC
 *   Transport header (FIR/FIN/Sequence)
 *   App header (Function Code) | App data
 */
class Dnp3Frame implements FrameInterface
{
    public const START_HI = 0x05;
    public const START_LO = 0x64;

    // DNP3 CRC-16 polynomial: 0x3D65 (same as CRC-16/DNP)
    private const CRC_POLY = 0x3D65;

    // Function codes
    public const FC_CONFIRM       = 0x00;
    public const FC_READ          = 0x01;
    public const FC_WRITE         = 0x02;
    public const FC_SELECT        = 0x03;
    public const FC_OPERATE       = 0x04;
    public const FC_DIRECT_OPERATE = 0x05;
    public const FC_DIRECT_OPERATE_NR = 0x06;
    public const FC_FREEZE        = 0x07;
    public const FC_FREEZE_NR     = 0x08;
    public const FC_FREEZE_CLEAR  = 0x09;
    public const FC_FREEZE_CLEAR_NR = 0x0A;
    public const FC_FREEZE_AT_TIME = 0x0B;
    public const FC_FREEZE_AT_TIME_NR = 0x0C;
    public const FC_COLD_RESTART  = 0x0D;
    public const FC_WARM_RESTART  = 0x0E;
    public const FC_RESPONSE      = 0x81;
    public const FC_UNSOLICITED   = 0x82;
    public const FC_AUTHENTICATE  = 0x83;

    // Transport flags
    public const TRANSPORT_FIR = 0x80;
    public const TRANSPORT_FIN = 0x40;
    public const TRANSPORT_SEQ_MASK = 0x3F;

    // Object group / variation presets
    public const GROUP_BINARY_INPUT        = 1;
    public const GROUP_BINARY_OUTPUT       = 10;
    public const GROUP_COUNTER             = 20;
    public const GROUP_ANALOG_INPUT        = 30;
    public const GROUP_ANALOG_OUTPUT       = 40;
    public const GROUP_TIME_DATE           = 50;
    public const GROUP_CLASS_OBJECT        = 60;

    private int $source;
    private int $destination;
    private int $functionCode;
    private int $transportFlags = 0x80 | 0x40; // FIR | FIN by default
    private string $appData;
    private array $parsedData;

    public function __construct(
        int $source = 1,
        int $destination = 1,
        int $functionCode = self::FC_READ,
        string $appData = '',
    ) {
        $this->source = $source;
        $this->destination = $destination;
        $this->functionCode = $functionCode;
        $this->appData = $appData;
        $this->parsedData = [];
    }

    public function getSource(): int { return $this->source; }
    public function getDestination(): int { return $this->destination; }
    public function getFunctionCode(): int { return $this->functionCode; }
    public function getFunctionName(): string { return self::functionName($this->functionCode); }
    public function getAppData(): string { return $this->appData; }
    public function getParsedData(): array { return $this->parsedData; }

    public function getData(): array
    {
        return [
            'source'        => $this->source,
            'destination'   => $this->destination,
            'function_code' => $this->functionCode,
            'function_name' => $this->getFunctionName(),
            'app_data'      => $this->appData,
            'parsed_data'   => $this->parsedData,
        ];
    }

    // -- Static factories --

    /**
     * Read request: class 0 poll or specific object.
     *
     * @param int $group    Object group (e.g., 30 = Analog Input)
     * @param int $variation Variation (e.g., 1 = 32-bit)
     * @param int $index    Point index
     */
    public static function readRequest(int $group = 60, int $variation = 1, int $index = 0): self
    {
        $data = '';
        if ($group === self::GROUP_CLASS_OBJECT) {
            // Class 0 poll: one-octet class header
            $data = chr($group) . chr(0x06);
        } else {
            // Specific object header: group | variation | qualifier
            $data = chr($group) . chr($variation) . chr(0x17) . chr(0x01);
            $data .= pack('V', $index); // 4-byte index
        }
        return new self(1, 1, self::FC_READ, $data);
    }

    /**
     * Select-before-operate: SELECT + OPERATE pair.
     * Returns the SELECT frame; caller must follow up with operate().
     */
    public static function select(int $group, int $variation, int $index, int $value): self
    {
        $data = chr($group) . chr($variation) . chr(0x17) . chr(0x01);
        $data .= pack('V', $index);
        $data .= pack('V', $value);
        $data .= "\x00"; // control status
        return new self(1, 1, self::FC_SELECT, $data);
    }

    /**
     * Operate command (after SELECT).
     */
    public static function operate(int $group, int $variation, int $index, int $value): self
    {
        $data = chr($group) . chr($variation) . chr(0x17) . chr(0x01);
        $data .= pack('V', $index);
        $data .= pack('V', $value);
        $data .= "\x00"; // control status
        return new self(1, 1, self::FC_OPERATE, $data);
    }

    /**
     * Direct operate (no select needed).
     */
    public static function directOperate(int $group, int $variation, int $index, int $value): self
    {
        $data = chr($group) . chr($variation) . chr(0x17) . chr(0x01);
        $data .= pack('V', $index);
        $data .= pack('V', $value);
        $data .= "\x00"; // control status
        return new self(1, 1, self::FC_DIRECT_OPERATE, $data);
    }

    // -- Encode / Decode --

    public function toBytes(): string
    {
        // Build the user data portion
        $transportByte = chr($this->transportFlags);
        $appHeader = chr($this->functionCode);

        // User data: transport + app header + app data
        $userData = $transportByte . $appHeader . $this->appData;

        // Link-layer frame
        $linkData = '';
        $linkData .= chr(self::START_HI) . chr(self::START_LO); // Start bytes
        $totalLen = 5 + strlen($userData); // control(1) + dest(2) + src(2) + CRC for header
        $linkData .= chr($totalLen);           // Length
        $linkData .= chr(0x44);                // Control byte (DIR=1, PRM=1, FCB=1)
        $linkData .= pack('v', $this->destination); // Destination
        $linkData .= pack('v', $this->source);       // Source
        $linkData .= self::crc16($linkData);          // Header CRC

        // Append user data
        $linkData .= $userData;

        // Append CRC-16 for user data portion (last 2 bytes of userData + CRC)
        // For simplicity, calculate CRC over userData only
        $dataCrc = self::crc16($userData);
        $linkData .= $dataCrc;

        return $linkData;
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 10) {
            throw new FrameException('DNP3 frame too short (min 10 bytes)');
        }

        $startHi = ord($bytes[0]);
        $startLo = ord($bytes[1]);
        if ($startHi !== self::START_HI || $startLo !== self::START_LO) {
            throw new FrameException(sprintf(
                'Invalid DNP3 start bytes: expected 0x0564, got 0x%02X%02X',
                $startHi, $startLo,
            ));
        }

        $length = ord($bytes[2]);
        $control = ord($bytes[3]);
        $destination = unpack('v', substr($bytes, 4, 2))[1];
        $source = unpack('v', substr($bytes, 6, 2))[1];

        // Header CRC is at bytes 8-9
        $headerCrc = unpack('v', substr($bytes, 8, 2))[1];

        // User data starts at byte 10
        $userData = substr($bytes, 10);
        if (strlen($userData) < 2) {
            throw new FrameException('DNP3 user data too short');
        }

        $transport = ord($userData[0]);
        $functionCode = ord($userData[1]);

        // App data is everything after transport + function code, minus 2-byte CRC
        $appData = '';
        if (strlen($userData) > 4) {
            $appData = substr($userData, 2, -2);
        }

        $frame = new self($source, $destination, $functionCode, $appData);
        $frame->transportFlags = $transport;

        // Parse object headers from app data
        if ($functionCode === self::FC_RESPONSE || $functionCode === self::FC_UNSOLICITED) {
            $frame->parsedData = self::parseResponseData($appData);
        }

        return $frame;
    }

    // -- CRC-16/DNP --

    /**
     * Compute CRC-16/DNP for a byte string.
     * Polynomial: 0x3D65, initial value: 0x0000, no XOR out.
     */
    public static function crc16(string $data): string
    {
        $crc = 0x0000;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x0001) {
                    $crc = ($crc >> 1) ^ self::CRC_POLY;
                } else {
                    $crc >>= 1;
                }
            }
        }
        // DNP3 expects CRC bytes in little-endian order
        return pack('v', $crc);
    }

    /**
     * Validate CRC-16/DNP for a byte string against expected CRC.
     * The expected CRC is the last 2 bytes (little-endian) of the data.
     */
    public static function validateCrc16(string $dataWithCrc): bool
    {
        if (strlen($dataWithCrc) < 2) return false;
        $data = substr($dataWithCrc, 0, -2);
        $expectedCrc = unpack('v', substr($dataWithCrc, -2, 2))[1];
        $computedRaw = self::crc16($data);
        $computed = unpack('v', $computedRaw)[1];
        return $computed === $expectedCrc;
    }

    // -- Helpers --

    public static function functionName(int $code): string
    {
        return match ($code) {
            self::FC_CONFIRM          => 'CONFIRM',
            self::FC_READ             => 'READ',
            self::FC_WRITE            => 'WRITE',
            self::FC_SELECT           => 'SELECT',
            self::FC_OPERATE          => 'OPERATE',
            self::FC_DIRECT_OPERATE   => 'DIRECT_OPERATE',
            self::FC_DIRECT_OPERATE_NR => 'DIRECT_OPERATE_NR',
            self::FC_FREEZE           => 'FREEZE',
            self::FC_RESPONSE         => 'RESPONSE',
            self::FC_UNSOLICITED      => 'UNSOLICITED_RESPONSE',
            default                   => 'UNKNOWN',
        };
    }

    public function isResponse(): bool
    {
        return $this->functionCode === self::FC_RESPONSE
            || $this->functionCode === self::FC_UNSOLICITED;
    }

    private static function parseResponseData(string $data): array
    {
        $result = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos + 3 <= $len) {
            $group = ord($data[$pos]);
            $variation = ord($data[$pos + 1]);
            $qualifier = ord($data[$pos + 2]);

            if ($qualifier === 0x06) {
                // No range field for class objects
                $result[] = ['group' => $group, 'variation' => $variation];
                $pos += 3;
            } elseif ($qualifier === 0x17) {
                // Indexed: count (1 byte) + 4-byte index
                if ($pos + 7 > $len) break;
                $count = ord($data[$pos + 3]);
                $index = unpack('V', substr($data, $pos + 4, 4))[1];
                $result[] = [
                    'group'     => $group,
                    'variation' => $variation,
                    'count'     => $count,
                    'index'     => $index,
                ];
                $pos += 8;
            } else {
                break; // Unknown qualifier
            }
        }

        return $result;
    }
}
