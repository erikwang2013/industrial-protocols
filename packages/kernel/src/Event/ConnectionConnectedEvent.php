<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace Erikwang2013\IndustrialProtocols\Event;
class ConnectionConnectedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $protocol,
        public readonly string $address,
    ) {}
}
