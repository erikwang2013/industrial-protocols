<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Event;

class GatewayRuleFailedEvent
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $message,
        public readonly int $failureCount,
    ) {}
}
