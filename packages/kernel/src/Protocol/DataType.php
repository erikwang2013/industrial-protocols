<?php

namespace IndustrialProtocols\Protocol;

enum DataType: string
{
    case BOOL    = 'BOOL';
    case INT16   = 'INT16';
    case UINT16  = 'UINT16';
    case INT32   = 'INT32';
    case UINT32  = 'UINT32';
    case FLOAT32 = 'FLOAT32';
    case FLOAT64 = 'FLOAT64';
    case STRING  = 'STRING';

    public function getSize(): int
    {
        return match ($this) {
            self::BOOL    => 1,
            self::INT16,
            self::UINT16  => 2,
            self::INT32,
            self::UINT32,
            self::FLOAT32 => 4,
            self::FLOAT64 => 8,
            self::STRING  => 0,
        };
    }
}
