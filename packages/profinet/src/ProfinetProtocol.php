<?php

namespace Erikwang2013\IndustrialProtocols\Profinet;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class ProfinetProtocol implements ProtocolInterface
{
    public function getName(): string { return 'profinet'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['nrt']; }
    public function getDefaultPort(): int { return 34964; }
    public function createConnector(array $config): ConnectorInterface { return new ProfinetConnector($config); }
}
