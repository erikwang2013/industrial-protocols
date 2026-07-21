<?php

namespace Erikwang2013\IndustrialProtocols\Hart\Frame;

use Erikwang2013\IndustrialProtocols\Hart\Exception\HartException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * HART Protocol Frame (Bell 202 FSK, 1200 baud on 4-20mA loops).
 *
 * Frame structure:
 *   Preamble (5-20 × 0xFF) + Delimiter (1) + Address (1 or 5) + Command (1)
 *   + ByteCount (1) + Data (0-255) + Checksum (1)
 *
 * Short frame: 1-byte address, long frame: 5-byte address.
 */
class HartFrame implements FrameInterface
{
    public const PREAMBLE_LENGTH = 5;
    public const DELIMITER_MASTER_TO_SLAVE = 0x02;
    public const DELIMITER_SLAVE_TO_MASTER = 0x06;
    public const DELIMITER_BURST = 0x01;

    /** Universal commands */
    public const CMD_READ_UNIQUE_ID = 0;
    public const CMD_READ_PV = 1;
    public const CMD_READ_LOOP_CURRENT = 2;
    public const CMD_READ_DYNAMIC_VARS = 3;
    public const CMD_READ_DEVICE_INFO = 13;
    public const CMD_READ_TAG_DESC_DATE = 13;
    public const CMD_READ_PV_SENSOR_INFO = 15;
    public const CMD_READ_MESSAGE = 12;

    private function __construct(
        private int $delimiter,
        private int $address,
        private int $command,
        private string $data,
        private bool $isLongFrame = false,
        private string $longAddress = '',
    ) {}

    /**
     * Create a universal command frame.
     */
    public static function universalCommand(int $address, int $cmd, string $data = ''): self
    {
        return new self(
            delimiter: self::DELIMITER_MASTER_TO_SLAVE,
            address: $address,
            command: $cmd,
            data: $data,
        );
    }

    /**
     * Create a burst-mode frame (solicited).
     */
    public static function burstCommand(int $address, int $cmd, string $data = ''): self
    {
        return new self(
            delimiter: self::DELIMITER_BURST,
            address: $address,
            command: $cmd,
            data: $data,
        );
    }

    /**
     * Parse raw HART bytes into a frame.
     */
    public static function parse(string $bytes): self
    {
        $len = strlen($bytes);
        if ($len < 9) {
            throw HartException::checksumError();
        }

        // Skip preamble (find first non-0xFF)
        $pos = 0;
        while ($pos < $len && ord($bytes[$pos]) === 0xFF) {
            $pos++;
        }
        if ($pos >= $len - 5) {
            throw HartException::checksumError();
        }

        // Validate checksum
        $checksum = ord($bytes[$len - 1]);
        $xor = 0;
        for ($i = $pos; $i < $len - 1; $i++) {
            $xor ^= ord($bytes[$i]);
        }
        if ($xor !== $checksum) {
            throw HartException::checksumError();
        }

        $delimiter = ord($bytes[$pos]);
        $addressByte = ord($bytes[$pos + 1]);
        $isLong = ($addressByte & 0x80) !== 0;

        $address = 0;
        $longAddr = '';
        if ($isLong) {
            // Long frame: 5-byte address
            $addrBytes = substr($bytes, $pos + 1, 5);
            $address = $addressByte & 0x3F; // polling address from first byte
            $longAddr = bin2hex(substr($addrBytes, 1, 4));
        } else {
            // Short frame: 1-byte address (bits 0-3 = polling address)
            $address = $addressByte & 0x0F;
        }

        $cmdOffset = $isLong ? 6 : 2;
        $command = ord($bytes[$pos + $cmdOffset]);
        $byteCount = ord($bytes[$pos + $cmdOffset + 1]);

        // Handle response status (first 2 data bytes are status in slave->master)
        $dataOffset = $pos + $cmdOffset + 2;
        $data = substr($bytes, $dataOffset, $byteCount);

        if ($delimiter === self::DELIMITER_SLAVE_TO_MASTER && $byteCount >= 2) {
            // Check response status codes
            $status0 = ord($data[0]);
            $status1 = ord($data[1]);
            if (($status0 & 0x40) || $status1 > 0) {
                $statusCode = $status0 & 0x3F;
                if ($statusCode > 0) {
                    throw HartException::fromStatusCode($statusCode);
                }
            }
        }

        return new self(
            delimiter: $delimiter,
            address: $address,
            command: $command,
            data: $data,
            isLongFrame: $isLong,
            longAddress: $longAddr,
        );
    }

    public function toBytes(): string
    {
        $preamble = str_repeat("\xFF", self::PREAMBLE_LENGTH);
        $addr = '';
        if ($this->isLongFrame && $this->longAddress !== '') {
            $addr = chr(0x80 | ($this->address & 0x3F)) . hex2bin($this->longAddress);
        } else {
            $addr = chr($this->address & 0x0F);
        }
        $cmd = chr($this->command);
        $dataLen = strlen($this->data);
        $byteCount = chr($dataLen);

        $frame = $preamble . chr($this->delimiter) . $addr . $cmd . $byteCount . $this->data;

        // XOR checksum from delimiter to end of data
        $checksum = 0;
        for ($i = self::PREAMBLE_LENGTH; $i < strlen($frame); $i++) {
            $checksum ^= ord($frame[$i]);
        }

        return $frame . chr($checksum);
    }

    public static function fromBytes(string $bytes): static
    {
        return self::parse($bytes);
    }

    public function getData(): array
    {
        return [
            'delimiter' => $this->delimiter,
            'address' => $this->address,
            'command' => $this->command,
            'is_long_frame' => $this->isLongFrame,
            'data_len' => strlen($this->data),
        ];
    }

    /**
     * Extract Field Device info from CMD 0 or 13 responses.
     */
    public function getFieldDeviceInfo(): array
    {
        if ($this->command === 0 && strlen($this->data) >= 12) {
            return [
                'manufacturer_id' => ord($this->data[1]),
                'device_type' => ord($this->data[2]),
                'device_id' => bin2hex(substr($this->data, 9, 3)),
                'preambles_required' => ord($this->data[3]),
                'hart_revision' => ord($this->data[8]),
            ];
        }
        return ['command' => $this->command, 'raw' => bin2hex($this->data)];
    }

    /**
     * Extract Primary Variable value (IEEE 754 float, CMD 1/3 response).
     */
    public function getPV(): ?float
    {
        if (strlen($this->data) >= 8 && $this->command === 1) {
            // Skip 2 status bytes, read 4 bytes of float at offset 3
            $unit = ord($this->data[2]);
            $floatBytes = substr($this->data, 3, 4);
            if (strlen($floatBytes) === 4) {
                $val = unpack('G', $floatBytes); // big-endian IEEE 754
                return $val[1] ?? null;
            }
        }
        if (strlen($this->data) >= 24 && $this->command === 3) {
            // CMD 3 returns up to 4 dynamic vars; PV is first
            $floatBytes = substr($this->data, 2, 4);
            if (strlen($floatBytes) === 4) {
                $val = unpack('G', $floatBytes);
                return $val[1] ?? null;
            }
        }
        return null;
    }

    /**
     * Extract loop current in mA (CMD 2/3 response).
     */
    public function getLoopCurrent(): ?float
    {
        if (strlen($this->data) >= 7 && $this->command === 2) {
            $floatBytes = substr($this->data, 3, 4);
            if (strlen($floatBytes) === 4) {
                $val = unpack('G', $floatBytes);
                return $val[1] ?? null;
            }
        }
        if (strlen($this->data) >= 24 && $this->command === 3) {
            // CMD 3: loop current is 3rd dynamic var (offset 10)
            $floatBytes = substr($this->data, 10, 4);
            if (strlen($floatBytes) === 4) {
                $val = unpack('G', $floatBytes);
                return $val[1] ?? null;
            }
        }
        return null;
    }

    public function getCommand(): int { return $this->command; }
    public function getAddress(): int { return $this->address; }
    public function getRawData(): string { return $this->data; }
}
