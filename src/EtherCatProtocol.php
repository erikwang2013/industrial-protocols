<?php

namespace Erikwang2013\IndustrialProtocols\EtherCat;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class EtherCatProtocol implements ProtocolInterface
{
    public function getName(): string
    {
        return 'ethercat';
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

    /**
     * Requires a BridgeInterface in config['bridge'].
     * Supports: ExternalProcessBridge (C/C++ SDK) or TcpGatewayBridge (gateway hardware)
     */
    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("EtherCAT requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'ethercat');
    }
}
