<?php

namespace IndustrialProtocols\Gateway;

class CircuitBreaker
{
    private int $failureCount = 0;
    private ?float $lastFailureTime = null;
    private ?float $openedAt = null;
    private bool $hasBeenOpened = false;

    public function __construct(
        private string $id,
        private int $failureThreshold = 5,
        private float $cooldownSeconds = 30.0,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function isOpen(): bool
    {
        if ($this->openedAt === null) {
            return false;
        }

        // Check if cooldown has elapsed → transition to half-open
        if (microtime(true) - $this->openedAt >= $this->cooldownSeconds) {
            $this->openedAt = null;
            return false; // HALF_OPEN
        }

        return true; // OPEN
    }

    public function recordSuccess(): void
    {
        $this->failureCount = 0;
        $this->openedAt = null;
        $this->hasBeenOpened = false;
    }

    public function recordFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);

        if ($this->failureCount >= $this->failureThreshold) {
            $this->openedAt = microtime(true);
            $this->hasBeenOpened = true;
        }
    }

    public function getState(): string
    {
        if ($this->isOpen()) {
            return 'OPEN';
        }
        if ($this->hasBeenOpened && $this->openedAt === null) {
            return 'HALF_OPEN';
        }
        return 'CLOSED';
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function reset(): void
    {
        $this->failureCount = 0;
        $this->lastFailureTime = null;
        $this->openedAt = null;
        $this->hasBeenOpened = false;
    }
}
