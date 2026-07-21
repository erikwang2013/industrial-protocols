<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Framework\Webman;

use Erikwang2013\IndustrialProtocols\Kernel;
use Workerman\Worker;

class ProtocolProcess
{
    private ?Kernel $kernel = null;

    public function onWorkerStart(Worker $worker): void
    {
        $configPath = config_path() . '/plugin/industrial-protocols/kernel/config/industrial-protocols.php';

        if (!file_exists($configPath)) {
            $configPath = __DIR__ . '/../../../config/industrial-protocols.php';
        }

        $this->kernel = new Kernel(['config_path' => $configPath]);

        // Register protocols from installed packages
        $this->kernel->getProtocolRegistry()->autoDiscover(
            base_path() . '/vendor/composer/installed.json'
        );

        $this->kernel->boot();
    }

    public function onWorkerStop(Worker $worker): void
    {
        $this->kernel?->shutdown();
    }
}
