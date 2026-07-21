<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Event;

class GatewayRuleStartedEvent
{
    public function __construct(
        public readonly string $ruleId,
        public readonly string $sourceDevice,
        public readonly string $targetDevice,
    ) {}
}
