<?php
namespace Erikwang2013\IndustrialProtocols\Event;
use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
class ConnectionStateChangedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly HealthStatus $oldStatus,
        public readonly HealthStatus $newStatus,
    ) {}
}
