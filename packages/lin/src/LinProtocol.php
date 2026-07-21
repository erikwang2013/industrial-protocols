<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Lin;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class LinProtocol implements ProtocolInterface
{
    public function getName(): string { return 'lin'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['master', 'slave']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new LinConnector($config);
    }
}
