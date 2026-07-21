<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\SaeJ1850;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class SaeJ1850Protocol implements ProtocolInterface
{
    public function getName(): string { return 'sae-j1850'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['pwm', 'vpw']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("SAE J1850 requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'sae-j1850');
    }
}
