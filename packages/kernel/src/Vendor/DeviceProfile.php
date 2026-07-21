<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Vendor;

class DeviceProfile
{
    /** @param array<string, mixed> $configOverrides */
    public function __construct(
        public readonly string $model,
        public readonly string $version = '',
        public readonly array  $configOverrides = [],
        public readonly string $description = '',
    ) {}
}
