<?php

namespace Erikwang2013\IndustrialProtocols\CcLinkIe;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class CcLinkIeProtocol implements ProtocolInterface
{
    public function getName(): string { return 'cc-link-ie'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['bridge']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("CC-Link IE requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'cc-link-ie');
    }
}
