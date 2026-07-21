<?php

namespace IndustrialProtocols\Retry;

interface RetryStrategyInterface
{
    public function shouldRetry(int $attempt, \Throwable $error): bool;
    public function getDelay(int $attempt): int; // ms
}
