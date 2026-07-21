<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Sercos1;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class Sercos1Protocol implements ProtocolInterface
{
    public function getName(): string { return 'sercos-i'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['fiber']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("SERCOS I/II requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'sercos-i');
    }
}
