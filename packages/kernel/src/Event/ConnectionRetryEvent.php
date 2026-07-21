<?php
namespace IndustrialProtocols\Event;
class ConnectionRetryEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly int $attempt,
        public readonly int $maxAttempts,
        public readonly int $delayMs,
    ) {}
}
