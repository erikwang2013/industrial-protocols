<?php

namespace Erikwang2013\IndustrialProtocols\KLine;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class KLineProtocol implements ProtocolInterface
{
    public function getName(): string { return 'k-line'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['iso9141', 'iso14230', 'kwp2000']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new KLineConnector($config);
    }
}
