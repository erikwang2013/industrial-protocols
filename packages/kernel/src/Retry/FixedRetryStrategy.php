<?php

namespace IndustrialProtocols\Retry;

class FixedRetryStrategy implements RetryStrategyInterface
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $delayMs = 1000,
        private array $retryableExceptions = [\Throwable::class],
    ) {}
    public function shouldRetry(int $attempt, \Throwable $error): bool
    {
        if ($attempt > $this->maxAttempts) return false;
        foreach ($this->retryableExceptions as $class) {
            if ($error instanceof $class) return true;
        }
        return false;
    }
    public function getDelay(int $attempt): int { return $this->delayMs; }
}
