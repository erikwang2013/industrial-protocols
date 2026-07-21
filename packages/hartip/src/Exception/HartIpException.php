<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\HartIp\Exception;

class HartIpException extends \RuntimeException
{
    public static function invalidVersion(int $version): self
    {
        return new self(sprintf('Invalid HART-IP protocol version: %d (expected 1)', $version));
    }

    public static function invalidMessageType(int $type): self
    {
        return new self(sprintf('Invalid HART-IP message type: %d', $type));
    }

    public static function headerTooShort(int $length): self
    {
        return new self(sprintf('HART-IP header too short: %d bytes (minimum 8)', $length));
    }

    public static function connectionFailed(string $address, int $port): self
    {
        return new self(sprintf('HART-IP connection failed: %s:%d', $address, $port));
    }
}
