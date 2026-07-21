<?php

namespace Erikwang2013\IndustrialProtocols\Framework\Laravel\Console;

use Illuminate\Console\Command;
use Erikwang2013\IndustrialProtocols\Kernel;

class GatewayListCommand extends Command
{
    protected $signature = 'industrial:gateway:list';
    protected $description = 'List all gateway rules';

    public function handle(Kernel $kernel): int
    {
        $rules = $kernel->getConfigRepository()->getGatewayRules();

        if (empty($rules)) {
            $this->info('No gateway rules configured.');
            return 0;
        }

        $this->table(['ID', 'Source Device', 'Source Point', 'Target Device', 'Target Point'], array_map(
            fn($r) => [$r['id'] ?? '?', $r['source_device'] ?? '?', $r['source_point'] ?? '?', $r['target_device'] ?? '?', $r['target_point'] ?? '?'],
            $rules,
        ));

        return 0;
    }
}
