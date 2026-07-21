<?php
namespace IndustrialProtocols\Event;
class DataReadEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly array $data,
        public readonly float $latencyMs,
    ) {}
}
