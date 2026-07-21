<?php

namespace IndustrialProtocols\Event;

class GatewayRuleStartedEvent
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $sourceDevice,
        public readonly string $targetDevice,
    ) {}
}
