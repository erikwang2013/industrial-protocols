<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class OpcUaProtocol implements ProtocolInterface
{
    public function getName(): string
    {
        return 'opc-ua';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getSupportedVariants(): array
    {
        return ['binary'];
    }

    public function getDefaultPort(): int
    {
        return 4840;
    }

    public function createConnector(array $config): ConnectorInterface
    {
        return new OpcUaConnector($config);
    }
}
