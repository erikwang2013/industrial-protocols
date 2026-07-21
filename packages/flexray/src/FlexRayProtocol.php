<?php

namespace Erikwang2013\IndustrialProtocols\FlexRay;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class FlexRayProtocol implements ProtocolInterface
{
    public function getName(): string { return 'flexray'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['bridge']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("FlexRay requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'flexray');
    }
}
