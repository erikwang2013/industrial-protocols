<?php

namespace Erikwang2013\IndustrialProtocols\CcLink;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class CcLinkProtocol implements ProtocolInterface
{
    public function getName(): string { return 'cc-link'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['rs485', 'v1', 'v2']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new CcLinkConnector($config);
    }
}
