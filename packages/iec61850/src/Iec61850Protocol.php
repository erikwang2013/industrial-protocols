<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Iec61850;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

/**
 * IEC 61850 Protocol.
 *
 * Supports three variants:
 *   - mms:   Pure PHP TCP driver via MMS (Manufacturing Message Specification) on port 102.
 *   - goose: Bridge-required (Layer 2 multicast, hardware timestamping).
 *   - sv:    Bridge-required (Sampled Values, high-speed streaming).
 */
class Iec61850Protocol implements ProtocolInterface
{
    public function getName(): string { return 'iec61850'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['mms', 'goose', 'sv']; }
    public function getDefaultPort(): int { return 102; }

    public function createConnector(array $config): ConnectorInterface
    {
        $variant = $config['variant'] ?? 'mms';

        if ($variant === 'mms') {
            return new Iec61850Connector($config);
        }

        // GOOSE and SV require a hardware bridge
        if (!isset($config['bridge'])) {
            throw new \RuntimeException(
                "IEC 61850 variant '{$variant}' requires a BridgeInterface in config['bridge']"
            );
        }
        return new BridgeConnector($config['bridge'], 'iec61850.' . $variant);
    }
}
