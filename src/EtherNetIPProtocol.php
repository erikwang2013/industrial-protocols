<?php

namespace IndustrialProtocols\EtherNetIP;

use IndustrialProtocols\Protocol\ConnectorInterface;
use IndustrialProtocols\Protocol\ProtocolInterface;

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
