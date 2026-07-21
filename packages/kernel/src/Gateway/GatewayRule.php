<?php

namespace Erikwang2013\IndustrialProtocols\Gateway;

class GatewayRule
{
    public function __construct(
        public readonly string $id,
        public readonly string $sourceDevice,
        public readonly string $sourcePoint,
        public readonly string $targetDevice,
        public readonly string $targetPoint,
        public readonly mixed $transform = null,
        public readonly string $trigger = 'poll', // 'poll' | 'change' | 'cron'
        public readonly int $interval = 1000,      // ms
        public readonly ?string $cronExpression = null,
        public readonly int $failureThreshold = 5,
        public readonly float $cooldownSeconds = 30.0,
    ) {}
}
