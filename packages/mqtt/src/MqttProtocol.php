<?php

namespace Erikwang2013\IndustrialProtocols\Mqtt;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class MqttProtocol implements ProtocolInterface
{
    public function getName(): string { return 'mqtt'; }
    public function getVersion(): string { return '3.1.1'; }
    public function getSupportedVariants(): array { return ['tcp', 'websocket']; }
    public function getDefaultPort(): int { return 1883; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new MqttConnector($config);
    }
}
