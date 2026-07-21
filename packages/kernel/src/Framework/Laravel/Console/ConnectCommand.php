<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Framework\Laravel\Console;

use Illuminate\Console\Command;
use Erikwang2013\IndustrialProtocols\Kernel;

class ConnectCommand extends Command
{
    protected $signature = 'industrial:connect {device : Device ID from config}';
    protected $description = 'Connect to an industrial device and verify health';

    public function handle(Kernel $kernel): int
    {
        $deviceId = $this->argument('device');

        try {
            $conn = $kernel->getConnectionManager()->connect($deviceId);
            $health = $kernel->getConnectionManager()->health($deviceId);

            $this->info("Connected to $deviceId");
            $this->table(['State', 'Latency (ms)', 'Last Error'], [
                [$health->state->value, $health->latencyMs, $health->lastError ?? 'N/A'],
            ]);

            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to connect to $deviceId: " . $e->getMessage());
            return 1;
        }
    }
}
