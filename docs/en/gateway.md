# Gateway Engine Guide

> [中文](../gateway.md)

## Configuration

```php
$engine->addRule(new GatewayRule(
    id: 'modbus-to-opcua',
    sourceDevice: 'plc-001', sourcePoint: '40001',
    targetDevice: 'opcua-server', targetPoint: 'ns=1;s=Temperature',
    transform: fn($v) => $v / 10,
    trigger: 'poll', interval: 1000,
));
```

## Trigger Modes

| Mode | Behavior |
|------|----------|
| `poll` | Read every N ms, write unconditionally |
| `change` | Write only when value changes |
| `cron` | Cron-scheduled batch sync |

`cron` uses a standard 5-field expression: `minute hour day month weekday`, supporting `*`, `a-b`, `a,b`, `*/n`, with minute resolution.

## Engine Lifecycle

```php
$engine = new GatewayEngine($connectionManager, $eventDispatcher, $coroutine, $log);
$engine->addRule($rule);
$result = $engine->executeOnce('rule-id');  // single
$results = $engine->tick();                 // all due rules (poll / change / cron)
$engine->run(tickIntervalMs: 100);          // continuous
$engine->stop();
```

## Value Transform Pipeline

```
Source Frame → Parse → Read → Transform → Encode → Target Write
```

```php
transform: fn($c) => $c * 9 / 5 + 32,  // C → F
transform: fn($raw) => $raw / 10,       // scale
```

## Circuit Breaker

States: `CLOSED → OPEN → HALF_OPEN`. Default: 5 failures → 30s cooldown.

## Events

`GatewayRuleStartedEvent` | `GatewayRuleCompletedEvent` | `GatewayRuleFailedEvent` | `GatewayCircuitBreakerEvent`
