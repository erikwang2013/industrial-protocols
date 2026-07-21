<?php
namespace IndustrialProtocols\Event;
use IndustrialProtocols\Connection\HealthStatus;
class ConnectionStateChangedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly HealthStatus $oldStatus,
        public readonly HealthStatus $newStatus,
    ) {}
}
