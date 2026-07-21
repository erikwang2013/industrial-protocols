<?php

namespace Erikwang2013\IndustrialProtocols\Isa100;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

/**
 * ISA100.11a Protocol — Industrial Wireless.
 *
 * Uses 802.15.4 wireless mesh for field device communication.
 * Requires specific radio hardware (Wireless Device Manager via a BridgeInterface).
 *
 * Key characteristics:
 *   - 2.4 GHz ISM band, DSSS modulation
 *   - IPv6 over low-power wireless (6LoWPAN)
 *   - Mesh routing with redundancy
 *   - Channel hopping for interference immunity
 */
class Isa100Protocol implements ProtocolInterface
{
    public function getName(): string { return 'isa100'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['wireless', 'backbone']; }
    public function getDefaultPort(): int { return 0; }

    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['bridge'])) {
            throw new \RuntimeException(
                "ISA100.11a requires a BridgeInterface in config['bridge'] " .
                "(e.g., a Wireless Device Manager gateway)"
            );
        }
        return new BridgeConnector($config['bridge'], 'isa100');
    }
}
