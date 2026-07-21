<?php

namespace Erikwang2013\IndustrialProtocols\IoLink;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class IoLinkProtocol implements ProtocolInterface
{
    public function getName(): string { return 'io-link'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['bridge']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("IO-Link requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'io-link');
    }
}
