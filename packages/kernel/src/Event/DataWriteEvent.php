<?php
namespace IndustrialProtocols\Event;
class DataWriteEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly array $values,
        public readonly float $latencyMs,
    ) {}
}
