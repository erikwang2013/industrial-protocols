<?php

namespace IndustrialProtocols\Framework;

class WebmanAdapter implements FrameworkAdapterInterface
{
    public function detect(): bool
    {
        return class_exists('Workerman\Worker');
    }

    public function getName(): string
    {
        return 'webman';
    }

    public function registerConfig(): void
    {
        // Webman auto-loads config from config/plugin/ directory
        // No explicit registration needed
    }

    public function registerServices(): void
    {
        // Services registered via config/plugin bootstrap
    }

    public function registerCommands(): void
    {
        // Webman doesn't have a command system like artisan
    }

    public function getConfigPath(): string
    {
        return base_path() . '/plugin/industrial-protocols/kernel/config/industrial-protocols.php';
    }

    public function isLongRunning(): bool
    {
        return true; // Webman is always long-running
    }
}
