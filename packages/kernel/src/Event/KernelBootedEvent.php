<?php
namespace Erikwang2013\IndustrialProtocols\Event;
class KernelBootedEvent {
    public function __construct(
        public readonly array $registeredProtocols = [],
        public readonly string $framework = 'plain',
    ) {}
}
