<?php
namespace IndustrialProtocols\Event;
class ConnectionConnectedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $protocol,
        public readonly string $address,
    ) {}
}
