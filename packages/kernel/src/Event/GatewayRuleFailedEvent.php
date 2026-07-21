<?php

namespace IndustrialProtocols\Event;

class GatewayRuleFailedEvent
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $message,
        public readonly int $failureCount,
    ) {}
}
