<?php

namespace Erikwang2013\IndustrialProtocols\Dnp3;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class Dnp3Protocol implements ProtocolInterface
{
    public function getName(): string { return 'dnp3'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['tcp', 'serial']; }
    public function getDefaultPort(): int { return 20000; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new Dnp3Connector($config);
    }
}
