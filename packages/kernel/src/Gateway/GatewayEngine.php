<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Gateway;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionManager;
use Erikwang2013\IndustrialProtocols\Log\LogDriverInterface;
use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineAdapterInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Erikwang2013\IndustrialProtocols\Event\GatewayRuleStartedEvent;
use Erikwang2013\IndustrialProtocols\Event\GatewayRuleCompletedEvent;
use Erikwang2013\IndustrialProtocols\Event\GatewayRuleFailedEvent;
use Erikwang2013\IndustrialProtocols\Event\GatewayCircuitBreakerEvent;

class GatewayEngine
{
    /** @var array<string, GatewayRule> */
    private array $rules = [];

    /** @var array<string, CircuitBreaker> */
    private array $breakers = [];

    /** @var array<string, array{source: mixed, target: mixed}> */
    private array $lastValues = [];

    private bool $running = false;

    public function __construct(
        private ConnectionManager $connectionManager,
        private EventDispatcherInterface $eventDispatcher,
        private CoroutineAdapterInterface $coroutine,
        private LogDriverInterface $log,
    ) {}

    public function addRule(GatewayRule $rule): void
    {
        $this->rules[$rule->id] = $rule;
        $this->breakers[$rule->id] = new CircuitBreaker(
            $rule->id,
            $rule->failureThreshold,
            $rule->cooldownSeconds,
        );
    }

    public function removeRule(string $ruleId): void
    {
        unset($this->rules[$ruleId], $this->breakers[$ruleId], $this->lastValues[$ruleId]);
    }

    /** @return array<string, GatewayRule> */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Execute all poll-triggered rules once.
     * For coroutine environments, rules execute in parallel.
     */
    public function tick(): array
    {
        $results = [];
        $tasks = [];

        foreach ($this->rules as $ruleId => $rule) {
            if ($rule->trigger !== 'poll') continue;

            $breaker = $this->breakers[$ruleId];
            if ($breaker->isOpen()) continue;

            $tasks[$ruleId] = function () use ($rule, $breaker, &$results, $ruleId) {
                $results[$ruleId] = $this->executeRule($rule, $breaker);
            };
        }

        if (!empty($tasks)) {
            $this->coroutine->parallel(array_values($tasks));
        }

        return $results;
    }

    /**
     * Run the gateway in a loop with the given interval.
     */
    public function run(int $tickIntervalMs = 100): void
    {
        $this->running = true;
        while ($this->running) {
            $this->tick();
            $this->coroutine->sleep($tickIntervalMs / 1000.0);
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function executeRule(GatewayRule $rule, CircuitBreaker $breaker): array
    {
        $start = microtime(true);

        try {
            $this->eventDispatcher->dispatch(new GatewayRuleStartedEvent(
                $rule->id, $rule->sourceDevice, $rule->targetDevice,
            ));

            // Read from source
            $sourceConn = $this->connectionManager->getConnection($rule->sourceDevice);
            if (!$sourceConn) {
                $sourceConn = $this->connectionManager->connect($rule->sourceDevice);
            }
            $sourceData = $sourceConn->read($rule->sourcePoint);

            // For 'change' trigger: skip if value hasn't changed
            if ($rule->trigger === 'change') {
                $prevSource = $this->lastValues[$rule->id]['source'] ?? null;
                $currentSource = $sourceData[$rule->sourcePoint] ?? null;
                if ($prevSource === $currentSource) {
                    return ['status' => 'skipped', 'rule' => $rule->id];
                }
                $this->lastValues[$rule->id]['source'] = $currentSource;
            }

            // Transform
            $value = $sourceData[$rule->sourcePoint] ?? null;
            if ($rule->transform !== null) {
                $value = ($rule->transform)($value);
            }

            // Write to target
            $targetConn = $this->connectionManager->getConnection($rule->targetDevice);
            if (!$targetConn) {
                $targetConn = $this->connectionManager->connect($rule->targetDevice);
            }
            $targetConn->write([$rule->targetPoint => $value], []);

            $latency = (microtime(true) - $start) * 1000;

            $breaker->recordSuccess();

            $this->eventDispatcher->dispatch(new GatewayRuleCompletedEvent(
                $rule->id, [$rule->sourcePoint => $value], $latency,
            ));

            $this->log->log('DEBUG', "Gateway rule {$rule->id}: {$rule->sourcePoint} → {$rule->targetPoint} = $value ({$latency}ms)");

            return ['status' => 'ok', 'rule' => $rule->id, 'value' => $value, 'latency_ms' => $latency];
        } catch (\Throwable $e) {
            $breaker->recordFailure();

            $this->eventDispatcher->dispatch(new GatewayRuleFailedEvent(
                $rule->id, $e->getMessage(), $breaker->getFailureCount(),
            ));

            if ($breaker->isOpen()) {
                $this->eventDispatcher->dispatch(new GatewayCircuitBreakerEvent($rule->id, 'OPENED'));
                $this->log->log('CRITICAL', "Gateway rule {$rule->id} circuit breaker OPENED after {$breaker->getFailureCount()} failures");
            }

            $this->log->log('ERROR', "Gateway rule {$rule->id} failed: " . $e->getMessage());

            return ['status' => 'error', 'rule' => $rule->id, 'error' => $e->getMessage()];
        }
    }

    /**
     * Execute a single rule immediately (for on-demand use).
     */
    public function executeOnce(string $ruleId): array
    {
        $rule = $this->rules[$ruleId] ?? null;
        if (!$rule) {
            throw new \RuntimeException("Gateway rule not found: $ruleId");
        }
        $breaker = $this->breakers[$ruleId];
        if ($breaker->isOpen()) {
            throw new \RuntimeException("Circuit breaker is open for rule: $ruleId");
        }
        return $this->executeRule($rule, $breaker);
    }
}
