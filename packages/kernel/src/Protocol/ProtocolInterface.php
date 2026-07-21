<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface ProtocolInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function getSupportedVariants(): array;
    public function getDefaultPort(): int;
    public function createConnector(array $config): ConnectorInterface;
}
