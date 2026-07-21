<?php

namespace IndustrialProtocols\Event;

class GatewayCircuitBreakerEvent
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $state, // 'OPENED' | 'CLOSED'
    ) {}
}
