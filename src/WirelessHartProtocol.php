<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\WirelessHart;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

/**
 * WirelessHART Protocol — Industrial Wireless over HART Application Layer.
 *
 * Uses 802.15.4-based wireless mesh networking on top of the HART application layer.
 * Requires a WirelessHART Gateway (BridgeInterface) to bridge to the mesh.
 *
 * Key characteristics:
 *   - 2.4 GHz ISM band, IEEE 802.15.4 PHY
 *   - TDMA with 10 ms time slots
 *   - Self-organizing mesh with redundant paths
 *   - HART application layer (commands 0-64770)
 *   - Channel hopping (15 channels)
 *   - AES-128 encryption
 *
 * Compatible with wired HART command set (universal, common practice, device-specific).
 */
class WirelessHartProtocol implements ProtocolInterface
{
    public function getName(): string { return 'wirelesshart'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['wireless', 'gateway']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException(
                "WirelessHART requires a BridgeInterface in config['bridge'] " .
                "(e.g., a WirelessHART Gateway such as Emerson 1420 or Pepperl+Fuchs WHA-GW)"
            );
        }
        return new BridgeConnector($config['bridge'], 'wirelesshart');
    }
}
