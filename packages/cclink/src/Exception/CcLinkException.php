<?php

namespace Erikwang2013\IndustrialProtocols\CcLink\Exception;

use Erikwang2013\IndustrialProtocols\Exception\ProtocolException;

class CcLinkException extends ProtocolException
{
    public static function timeout(string $station): self
    {
        return new self("CC-Link timeout waiting for station: $station", ['station' => $station]);
    }

    public static function stationError(int $station, int $errorCode): self
    {
        $messages = [
            0x01 => 'Station not found',
            0x02 => 'Station busy',
            0x04 => 'CRC error',
            0x08 => 'Overrun error',
            0x10 => 'Framing error',
            0x20 => 'Transmission error',
        ];
        $msg = $messages[$errorCode] ?? "Unknown error code: $errorCode";
        return new self("CC-Link station $station error: $msg", [
            'station' => $station,
            'error_code' => $errorCode,
        ]);
    }
}
