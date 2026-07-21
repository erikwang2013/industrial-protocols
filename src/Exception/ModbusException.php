<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Modbus\Exception;

use Erikwang2013\IndustrialProtocols\Exception\ProtocolException;

class ModbusException extends ProtocolException
{
    public static function fromErrorCode(int $functionCode, int $exceptionCode): self
    {
        $messages = [
            0x01 => 'Illegal function',
            0x02 => 'Illegal data address',
            0x03 => 'Illegal data value',
            0x04 => 'Server device failure',
            0x06 => 'Server device busy',
        ];
        $msg = $messages[$exceptionCode] ?? "Unknown exception code: 0x" . dechex($exceptionCode);
        return new self("Modbus error (FC=0x" . dechex($functionCode) . "): $msg", [
            'function_code' => $functionCode,
            'exception_code' => $exceptionCode,
        ]);
    }
}
