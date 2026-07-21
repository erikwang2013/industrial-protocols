<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Pci;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class PciProtocol implements ProtocolInterface
{
    public function getName(): string { return 'pci'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['pci', 'pcie']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException("PCI/PCIe requires a BridgeInterface in config['bridge']");
        }
        return new BridgeConnector($config['bridge'], 'pci');
    }
}
