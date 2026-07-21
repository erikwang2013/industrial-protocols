<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Event;

class GatewayRuleCompletedEvent
{
    public function __construct(
        public readonly string $ruleId,
        public readonly array $data,
        public readonly float $latencyMs,
    ) {}
}
