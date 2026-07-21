<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace Erikwang2013\IndustrialProtocols\Event;
class ConnectionRetryEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly int $attempt,
        public readonly int $maxAttempts,
        public readonly int $delayMs,
    ) {}
}
