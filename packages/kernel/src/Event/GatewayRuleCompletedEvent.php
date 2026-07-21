<?php

namespace Erikwang2013\IndustrialProtocols\Event;

class GatewayRuleCompletedEvent
{
    public function __construct(
        public readonly string $ruleId,
        public readonly array $data,
        public readonly float $latencyMs,
    ) {}
}
