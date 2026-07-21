<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\EtherNetIP;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class EtherNetIPProtocol implements ProtocolInterface
{
    public function getName(): string
    {
        return 'ethernet-ip';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getSupportedVariants(): array
    {
        return ['tcp'];
    }

    public function getDefaultPort(): int
    {
        return 44818;
    }

    public function createConnector(array $config): ConnectorInterface
    {
        return new EtherNetIPConnector($config);
    }
}
