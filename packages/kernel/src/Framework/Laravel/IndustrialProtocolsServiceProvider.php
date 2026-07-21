<?php

namespace Erikwang2013\IndustrialProtocols\Framework\Laravel;

use Erikwang2013\IndustrialProtocols\Kernel;
use Illuminate\Support\ServiceProvider;

class IndustrialProtocolsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../../config/industrial-protocols.php',
            'industrial-protocols',
        );

        $this->app->singleton(Kernel::class, function ($app) {
            $kernel = new Kernel([
                'config_path' => config_path('industrial-protocols.php'),
            ]);
            return $kernel;
        });

        $this->app->singleton('industrial-protocols', function ($app) {
            return $app->make(Kernel::class);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../../config/industrial-protocols.php' => config_path('industrial-protocols.php'),
        ], 'industrial-protocols-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ConnectCommand::class,
                Console\GatewayListCommand::class,
            ]);
        }

        // Auto-boot kernel
        $kernel = $this->app->make(Kernel::class);
        $kernel->boot();
    }
}
