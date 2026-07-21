<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Powerlink;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class PowerlinkProtocol implements ProtocolInterface
{
    public function getName(): string
    {
        return 'powerlink';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getSupportedVariants(): array
    {
        return ['bridge'];
    }

    public function getDefaultPort(): int
    {
        return 0;
    }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("POWERLINK requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'powerlink');
    }
}
