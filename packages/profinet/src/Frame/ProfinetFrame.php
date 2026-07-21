<?php

namespace Erikwang2013\IndustrialProtocols\Profinet\Frame;

use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class ProfinetFrame implements FrameInterface
{
    private function __construct(
        private int $frameId,
        private string $serviceId,
        private array $blocks = [],
    ) {}

    /**
     * DCP Identify Request — discover Profinet devices on the network.
     * Frame ID 0xFEFE, Service ID 0x00 (Identify).
     */
    public static function dcpIdentify(): self
    {
        return new self(0xFEFE, "\x00\x00", [
            ['option' => 2, 'suboption' => 1], // DeviceNameOfStation (all)
        ]);
    }

    /**
     * Read Record request.
     * @param int $api Access Point Index
     * @param int $slot Module slot
     * @param int $subslot Submodule
     * @param int $index Record data index
     */
    public static function readRecord(int $api = 0, int $slot = 0, int $subslot = 1, int $index = 0): self
    {
        return new self(0xFEFD, "\x01\x00", [ // Read Record
            'api'     => $api,
            'slot'    => $slot,
            'subslot' => $subslot,
            'index'   => $index,
        ]);
    }

    /**
     * Write Record request.
     * @param string $data Data to write
     */
    public static function writeRecord(int $api, int $slot, int $subslot, int $index, string $data): self
    {
        return new self(0xFEFD, "\x02\x00", [
            'api'     => $api,
            'slot'    => $slot,
            'subslot' => $subslot,
            'index'   => $index,
            'data'    => $data,
        ]);
    }

    public function toBytes(): string
    {
        $body = $this->serviceId;

        if ($this->frameId === 0xFEFE) {
            // DCP: service_id(2) + service_type(1) + xid(4) + response_delay(2) + dcp_length(2)
            $body .= chr(0) // ServiceType: Request
                  . pack('N', random_int(0, 0x7FFFFFFF)) // XID
                  . pack('v', 0) // ResponseDelay
                  . pack('v', 0); // DCPDataLength (set later)
            foreach ($this->blocks as $block) {
                $body .= pack('v', $block['option'])
                      . pack('v', $block['suboption'])
                      . pack('v', 0); // block length 0 = wildcard
            }
        } elseif ($this->frameId === 0xFEFD) {
            // Record Data
            $body .= pack('v', $this->blocks['api'])
                  . pack('v', $this->blocks['slot'])
                  . pack('v', $this->blocks['subslot'])
                  . pack('v', 0) // padding
                  . pack('V', $this->blocks['index'])
                  . pack('V', strlen($this->blocks['data'] ?? '')); // data length
            if (isset($this->blocks['data'])) {
                $body .= $this->blocks['data'];
            }
        }

        return pack('v', $this->frameId) . pack('v', strlen($body)) . $body;
    }

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 4) {
            throw new \RuntimeException('Profinet frame too short');
        }

        $frameId = unpack('v', substr($bytes, 0, 2))[1];
        $length  = unpack('v', substr($bytes, 2, 2))[1];
        $body    = substr($bytes, 4, $length);
        $serviceId = substr($body, 0, 2);

        $blocks = [];
        if ($frameId === 0xFEFE && strlen($body) > 12) {
            // Parse DCP response blocks
            $pos = 12;
            while ($pos + 6 <= strlen($body)) {
                $option    = unpack('v', substr($body, $pos, 2))[1];
                $suboption = unpack('v', substr($body, $pos + 2, 2))[1];
                $blockLen  = unpack('v', substr($body, $pos + 4, 2))[1];
                $pos += 6;
                $blockData = $blockLen > 0 ? substr($body, $pos, $blockLen) : '';
                $pos += $blockLen;
                $blocks[] = compact('option', 'suboption', 'blockLen', 'blockData');
            }
        }

        return new self($frameId, $serviceId, $blocks);
    }

    public function getData(): array
    {
        return ['frame_id' => $this->frameId, 'blocks' => $this->blocks];
    }

    public function getBlocks(): array { return $this->blocks; }
    public function getFrameId(): int { return $this->frameId; }
}
