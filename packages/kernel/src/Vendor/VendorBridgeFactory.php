<?php

namespace Erikwang2013\IndustrialProtocols\Vendor;

use Erikwang2013\IndustrialProtocols\Bridge\BridgeInterface;
use Erikwang2013\IndustrialProtocols\Bridge\ExternalProcessBridge;
use Erikwang2013\IndustrialProtocols\Bridge\TcpGatewayBridge;

class VendorBridgeFactory
{
    /** @var array<string, VendorProfile> */
    private array $vendors = [];

    public function register(VendorProfile $vendor): self
    {
        $this->vendors[$vendor->name] = $vendor;
        return $this;
    }

    /** @return array<string, VendorProfile> */
    public function listVendors(): array { return $this->vendors; }

    public function getVendor(string $name): ?VendorProfile { return $this->vendors[$name] ?? null; }

    /** @return DeviceProfile[] */
    public function getDevices(string $vendorName): array { return $this->getVendor($vendorName)?->devices ?? []; }

    /**
     * Create a bridge for a specific vendor + device + version.
     */
    public function create(
        string $vendorName,
        string $deviceModel = '',
        string $version = '',
        array  $overrides = [],
    ): BridgeInterface {
        $vendor = $this->getVendor($vendorName);
        if (!$vendor) throw new \RuntimeException("Unknown vendor: $vendorName. Available: " . implode(', ', array_keys($this->vendors)));

        $device = $deviceModel ? $vendor->getDevice($deviceModel) : null;

        // Merge: vendor defaults → device overrides → user overrides
        $config = array_merge(
            ['host' => '127.0.0.1', 'port' => $vendor->defaultPort],
            $device?->configOverrides ?? [],
            $overrides,
        );

        if ($vendor->bridgeType === 'external-process') {
            $sdkPath = $config['sdk_path'] ?? $vendor->sdkPath;
            return new ExternalProcessBridge(
                $sdkPath,
                $config['work_dir'] ?? '',
                array_merge($vendor->envVars, $config['env'] ?? []),
                (float)($config['startup_timeout'] ?? 10.0),
            );
        }

        return new TcpGatewayBridge(
            $config['host'],
            $config['port'],
            (float)($config['timeout'] ?? 5.0),
            $config['transport'] ?? 'tcp',
        );
    }

    /**
     * Create with device config array from ConnectionManager config.
     */
    public function createFromConfig(array $deviceConfig): BridgeInterface
    {
        return $this->create(
            $deviceConfig['vendor'] ?? '',
            $deviceConfig['device_model'] ?? '',
            $deviceConfig['device_version'] ?? '',
            $deviceConfig,
        );
    }
}
