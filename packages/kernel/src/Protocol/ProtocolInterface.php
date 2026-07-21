<?php

namespace IndustrialProtocols\Protocol;

interface ProtocolInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function getSupportedVariants(): array;
    public function getDefaultPort(): int;
    public function createConnector(array $config): ConnectorInterface;
}
