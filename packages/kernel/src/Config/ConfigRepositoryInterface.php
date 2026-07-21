<?php

namespace Erikwang2013\IndustrialProtocols\Config;

interface ConfigRepositoryInterface
{
    public function getDeviceConfig(string $deviceId): array;
    public function setDeviceConfig(string $deviceId, array $config): void;
    public function removeDeviceConfig(string $deviceId): void;
    public function getAllDeviceConfigs(): array;
    public function getDataPoints(string $deviceId): array;
    public function setDataPoints(string $deviceId, array $points): void;
    public function getGatewayRules(): array;
    public function addGatewayRule(array $rule): void;
    public function removeGatewayRule(string $ruleId): void;
}
