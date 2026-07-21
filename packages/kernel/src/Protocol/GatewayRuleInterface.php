<?php

namespace IndustrialProtocols\Protocol;

interface GatewayRuleInterface
{
    public function getSource(): ConnectorInterface;
    public function getTarget(): ConnectorInterface;
    public function getMapping(): array;
    public function getTransform(): ?callable;
    public function getInterval(): int;
}
