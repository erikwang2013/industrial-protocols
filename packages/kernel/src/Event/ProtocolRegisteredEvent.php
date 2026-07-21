<?php
namespace Erikwang2013\IndustrialProtocols\Event;
class ProtocolRegisteredEvent {
    public function __construct(
        public readonly string $protocolName,
        public readonly string $protocolClass,
    ) {}
}
