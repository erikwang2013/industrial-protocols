<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Retry;

class NoRetryStrategy implements RetryStrategyInterface
{
    public function shouldRetry(int $attempt, \Throwable $error): bool { return false; }
    public function getDelay(int $attempt): int { return 0; }
}
