<?php
namespace Erikwang2013\IndustrialProtocols\Event;
class ConnectionDisconnectedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $reason = '',
    ) {}
}
