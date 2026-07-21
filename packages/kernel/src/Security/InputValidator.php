<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace Erikwang2013\IndustrialProtocols\Security;

class InputValidator
{
    /**
     * Validate device ID: alphanumeric + dash + underscore, max 128 chars.
     */
    public static function deviceId(string $id): string
    {
        $id = trim($id);
        if ($id === '') throw new \InvalidArgumentException('Device ID cannot be empty');
        if (strlen($id) > 128) throw new \InvalidArgumentException('Device ID too long (max 128 chars)');
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $id)) throw new \InvalidArgumentException('Device ID contains invalid characters');
        return $id;
    }

    /**
     * Validate host: IP address or hostname.
     */
    public static function host(string $host): string
    {
        $host = trim($host);
        if ($host === '') throw new \InvalidArgumentException('Host cannot be empty');
        if (strlen($host) > 255) throw new \InvalidArgumentException('Host too long');
        return $host;
    }

    /**
     * Validate port number.
     */
    public static function port(int $port): int
    {
        if ($port < 1 || $port > 65535) throw new \InvalidArgumentException("Port must be 1-65535, got $port");
        return $port;
    }

    /**
     * Validate Modbus register address.
     */
    public static function modbusAddress(string $address): string
    {
        $addr = (int)$address;
        if ($addr < 0 || $addr > 65535) throw new \InvalidArgumentException("Register address out of range: $address");
        return $address;
    }

    /**
     * Validate timeout in milliseconds.
     */
    public static function timeout(int $ms): int
    {
        if ($ms < 10 || $ms > 60000) throw new \InvalidArgumentException("Timeout must be 10-60000ms, got $ms");
        return $ms;
    }

    /**
     * Validate frame size against protocol limits.
     */
    public static function frameSize(string $bytes, int $maxBytes = 260): void
    {
        $len = strlen($bytes);
        if ($len > $maxBytes) throw new \InvalidArgumentException("Frame too large: $len bytes (max $maxBytes)");
    }
}
