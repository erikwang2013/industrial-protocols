<?php

namespace Erikwang2013\IndustrialProtocols\HartIp;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class HartIpProtocol implements ProtocolInterface
{
    public function getName(): string { return 'hart-ip'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['tcp', 'udp']; }
    public function getDefaultPort(): int { return 5094; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new HartIpConnector($config);
    }
}
