<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface GatewayRuleInterface
{
    public function getSource(): ConnectorInterface;
    public function getTarget(): ConnectorInterface;
    public function getMapping(): array;
    public function getTransform(): ?callable;
    public function getInterval(): int;
}
