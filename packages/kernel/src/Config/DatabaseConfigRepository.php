<?php

namespace IndustrialProtocols\Config;

class DatabaseConfigRepository implements ConfigRepositoryInterface
{
    private array $cache = [];
    private bool $loaded = false;

    public function __construct(private \PDO $pdo, private string $tablePrefix = 'industrial_') {}

    private function ensureTable(): void
    {
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tablePrefix}devices (
            device_id VARCHAR(128) PRIMARY KEY,
            config TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tablePrefix}gateway_rules (
            rule_id VARCHAR(128) PRIMARY KEY,
            config TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
    }

    private function loadAll(): void
    {
        if ($this->loaded) return;
        $this->ensureTable();

        $stmt = $this->pdo->query("SELECT device_id, config FROM {$this->tablePrefix}devices");
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->cache['devices'][$row['device_id']] = json_decode($row['config'], true);
        }

        $this->loaded = true;
    }

    public function getDeviceConfig(string $deviceId): array
    {
        $this->loadAll();
        if (!isset($this->cache['devices'][$deviceId])) {
            throw new \RuntimeException("Device config not found: $deviceId");
        }
        return $this->cache['devices'][$deviceId];
    }

    public function setDeviceConfig(string $deviceId, array $config): void
    {
        $this->ensureTable();
        $json = json_encode($config);
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO {$this->tablePrefix}devices (device_id, config, updated_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$deviceId, $json]);
        $this->cache['devices'][$deviceId] = $config;
    }

    public function removeDeviceConfig(string $deviceId): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tablePrefix}devices WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        unset($this->cache['devices'][$deviceId]);
    }

    public function getAllDeviceConfigs(): array
    {
        $this->loadAll();
        return $this->cache['devices'] ?? [];
    }

    public function getDataPoints(string $deviceId): array
    {
        return $this->getDeviceConfig($deviceId)['points'] ?? [];
    }

    public function setDataPoints(string $deviceId, array $points): void
    {
        $config = $this->getDeviceConfig($deviceId);
        $config['points'] = $points;
        $this->setDeviceConfig($deviceId, $config);
    }

    public function getGatewayRules(): array
    {
        $this->ensureTable();
        $stmt = $this->pdo->query("SELECT rule_id, config FROM {$this->tablePrefix}gateway_rules");
        $rules = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $rules[] = json_decode($row['config'], true);
        }
        return $rules;
    }

    public function addGatewayRule(array $rule): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO {$this->tablePrefix}gateway_rules (rule_id, config) VALUES (?, ?)");
        $stmt->execute([$rule['id'] ?? uniqid('rule_'), json_encode($rule)]);
    }

    public function removeGatewayRule(string $ruleId): void
    {
        $this->ensureTable();
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tablePrefix}gateway_rules WHERE rule_id = ?");
        $stmt->execute([$ruleId]);
    }
}
