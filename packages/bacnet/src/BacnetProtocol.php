<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Bacnet;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class BacnetProtocol implements ProtocolInterface
{
    public function getName(): string
    {
        return 'bacnet';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getSupportedVariants(): array
    {
        return ['ip'];
    }

    public function getDefaultPort(): int
    {
        return 47808;
    }

    public function createConnector(array $config): ConnectorInterface
    {
        return new BacnetConnector($config);
    }
}
