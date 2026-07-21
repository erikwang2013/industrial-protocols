<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Modbus;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class ModbusProtocol implements ProtocolInterface
{
    public function getName(): string { return 'modbus'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['tcp', 'rtu', 'ascii']; }
    public function getDefaultPort(): int { return 502; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new ModbusConnector($config);
    }
}
