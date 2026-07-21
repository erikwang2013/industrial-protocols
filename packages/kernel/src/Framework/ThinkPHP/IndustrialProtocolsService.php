<?php

namespace Erikwang2013\IndustrialProtocols\Framework\ThinkPHP;

use Erikwang2013\IndustrialProtocols\Kernel;

class IndustrialProtocolsService
{
    private static ?Kernel $kernel = null;

    public static function boot(?string $configPath = null): Kernel
    {
        if (self::$kernel === null) {
            $configPath ??= app()->getRootPath() . 'config/industrial-protocols.php';

            self::$kernel = new Kernel(['config_path' => $configPath]);
            self::$kernel->getProtocolRegistry()->autoDiscover(
                app()->getRootPath() . 'vendor/composer/installed.json'
            );
            self::$kernel->boot();
        }
        return self::$kernel;
    }

    public static function kernel(): ?Kernel
    {
        return self::$kernel;
    }

    public static function shutdown(): void
    {
        self::$kernel?->shutdown();
        self::$kernel = null;
    }
}
