<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Retry;

interface RetryStrategyInterface
{
    public function shouldRetry(int $attempt, \Throwable $error): bool;
    public function getDelay(int $attempt): int; // ms
}
