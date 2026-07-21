<?php

namespace Erikwang2013\IndustrialProtocols\Vendor;

class VendorProfile
{
    /** @param DeviceProfile[] $devices */
    public function __construct(
        public readonly string $name,
        public readonly string $protocol,
        public readonly string $bridgeType, // 'external-process' | 'tcp-gateway'
        public readonly string $sdkPath,
        public readonly int $defaultPort,
        public readonly array  $devices = [],
        public readonly array  $envVars = [],
        public readonly string $homepage = '',
        public readonly string $description = '',
    ) {}

    public function getDevice(string $model): ?DeviceProfile
    {
        foreach ($this->devices as $d) {
            if ($d->model === $model) return $d;
        }
        return null;
    }
}
