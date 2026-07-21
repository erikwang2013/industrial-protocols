<?php

namespace IndustrialProtocols\Framework;

class LaravelAdapter implements FrameworkAdapterInterface
{
    private ?string $configPath = null;

    public function detect(): bool
    {
        return class_exists('Illuminate\Foundation\Application');
    }

    public function getName(): string
    {
        return 'laravel';
    }

    public function registerConfig(): void
    {
        // Laravel handles config via ServiceProvider::publishes()
    }

    public function registerServices(): void
    {
        // Service binding is done in the ServiceProvider, not here
    }

    public function registerCommands(): void
    {
        // Commands are registered in the ServiceProvider
    }

    public function getConfigPath(): string
    {
        if ($this->configPath === null) {
            $this->configPath = function_exists('config_path')
                ? config_path('industrial-protocols.php')
                : base_path('config/industrial-protocols.php');
        }
        return $this->configPath;
    }

    public function isLongRunning(): bool
    {
        return function_exists('app') && app()->runningInConsole() === false && class_exists('Laravel\Octane\Octane');
    }
}
