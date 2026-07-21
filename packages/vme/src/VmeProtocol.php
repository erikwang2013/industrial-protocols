<?php

namespace Erikwang2013\IndustrialProtocols\Vme;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class VmeProtocol implements ProtocolInterface
{
    public function getName(): string { return 'vme'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['vme', 'vpx']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("VME/VPX requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'vme');
    }
}
