<?php
namespace IndustrialProtocols\Event;
class ConnectionDisconnectedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $reason = '',
    ) {}
}
