<?php

namespace IndustrialProtocols\Framework;

class ThinkPHPAdapter implements FrameworkAdapterInterface
{
    public function detect(): bool
    {
        return class_exists('think\App');
    }

    public function getName(): string
    {
        return 'thinkphp';
    }

    public function registerConfig(): void
    {
        // ThinkPHP auto-loads config from config/ directory
    }

    public function registerServices(): void
    {
        // Services registered via ThinkPHP service provider
    }

    public function registerCommands(): void
    {
        // ThinkPHP commands are registered via console.php or service
    }

    public function getConfigPath(): string
    {
        return app()->getConfigPath() . 'industrial-protocols.php';
    }

    public function isLongRunning(): bool
    {
        return false;
    }
}
