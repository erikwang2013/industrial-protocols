<?php
namespace IndustrialProtocols\Event;
class DataErrorEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $operation,
        public readonly string $message,
        public readonly int $retryCount,
    ) {}
}
