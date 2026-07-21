<?php

namespace IndustrialProtocols\Config;

use RuntimeException;

class FileConfigRepository implements ConfigRepositoryInterface
{
    private array $config;

    public function __construct(private string $configPath)
    {
        if (!file_exists($configPath)) {
            throw new RuntimeException("Config file not found: $configPath");
        }
        $this->config = require $configPath;
    }

    public function getDeviceConfig(string $deviceId): array
    {
        if (!isset($this->config['devices'][$deviceId])) {
            throw new RuntimeException("Device config not found: $deviceId");
        }
        return $this->config['devices'][$deviceId];
    }

    public function setDeviceConfig(string $deviceId, array $config): void
    {
        $this->config['devices'][$deviceId] = $config;
    }

    public function removeDeviceConfig(string $deviceId): void
    {
        unset($this->config['devices'][$deviceId]);
    }

    public function getAllDeviceConfigs(): array
    {
        return $this->config['devices'] ?? [];
    }

    public function getDataPoints(string $deviceId): array
    {
        return $this->getDeviceConfig($deviceId)['points'] ?? [];
    }

    public function setDataPoints(string $deviceId, array $points): void
    {
        $this->config['devices'][$deviceId]['points'] = $points;
    }

    public function getGatewayRules(): array
    {
        return $this->config['gateway']['rules'] ?? [];
    }

    public function addGatewayRule(array $rule): void
    {
        $this->config['gateway']['rules'][] = $rule;
    }

    public function removeGatewayRule(string $ruleId): void
    {
        $this->config['gateway']['rules'] = array_values(array_filter(
            $this->config['gateway']['rules'] ?? [],
            fn(array $rule) => ($rule['id'] ?? '') !== $ruleId,
        ));
    }
}
