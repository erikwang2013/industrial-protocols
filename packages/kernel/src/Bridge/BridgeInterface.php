<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Bridge;

interface BridgeInterface
{
    /**
     * Initialize the bridge connection.
     */
    public function open(): void;

    /**
     * Close the bridge connection.
     */
    public function close(): void;

    /**
     * Send raw data/command to the hardware and receive response.
     * @return string Raw response from hardware
     */
    public function execute(string $command, string|array $data = ''): string;

    /**
     * Check if the bridge is ready.
     */
    public function isReady(): bool;

    /**
     * Get bridge type identifier.
     */
    public function getType(): string;
}
