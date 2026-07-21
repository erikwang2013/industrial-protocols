<?php

namespace Erikwang2013\IndustrialProtocols\Hart\Exception;

use Erikwang2013\IndustrialProtocols\Exception\ProtocolException;

class HartException extends ProtocolException
{
    public static function fromStatusCode(int $statusCode): self
    {
        $messages = [
            0 => 'No command-specific error',
            1 => 'Undefined command',
            2 => 'Invalid selection',
            3 => 'Passed parameter too large',
            4 => 'Passed parameter too small',
            5 => 'Too few data bytes received',
            6 => 'Device-specific command error',
            7 => 'In write-protect mode',
            8 => 'Access restricted',
            16 => 'Device is busy',
            32 => 'Command not implemented',
            64 => 'Parity or checksum error',
        ];

        $msg = $messages[$statusCode] ?? "Unknown HART status code: $statusCode";
        return new self("HART error: $msg", ['status_code' => $statusCode]);
    }

    public static function checksumError(): self
    {
        return new self('HART frame checksum mismatch');
    }
}
