<?php

namespace Erikwang2013\IndustrialProtocols\Connection;

class HealthStatus implements \JsonSerializable
{
    public function __construct(
        public readonly ConnectionState $state,
        public readonly float $latencyMs = 0.0,
        public readonly ?string $lastError = null,
        public readonly int $retryCount = 0,
    ) {}

    public static function healthy(float $latencyMs): self
    {
        return new self(ConnectionState::HEALTHY, latencyMs: $latencyMs);
    }

    public static function degraded(float $latencyMs, string $error, int $retryCount): self
    {
        return new self(ConnectionState::DEGRADED, $latencyMs, $error, $retryCount);
    }

    public static function fault(string $error, int $retryCount): self
    {
        return new self(ConnectionState::FAULT, lastError: $error, retryCount: $retryCount);
    }

    public static function closed(string $reason): self
    {
        return new self(ConnectionState::CLOSED, lastError: $reason);
    }

    public function jsonSerialize(): array
    {
        return [
            'state'       => $this->state->value,
            'latency_ms'  => $this->latencyMs,
            'last_error'  => $this->lastError,
            'retry_count' => $this->retryCount,
        ];
    }
}
