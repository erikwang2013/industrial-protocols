# Gateway Engine Guide

> [English](en/gateway.md)

The Gateway Engine forwards data between industrial devices, translating between different protocols, value ranges, and trigger modes. It is a core Phase 2 component of the kernel.

## Configuration

```php
'gateway' => [
    'rules' => [
        [
            'id'             => 'modbus-to-opcua',
            'source_device'  => 'plc-001',
            'source_point'   => '40001',
            'target_device'  => 'opcua-server',
            'target_point'   => 'ns=1;s=Temperature',
            'transform'      => null,       // callable for value transform
            'trigger'        => 'poll',     // poll | change | cron
            'interval'       => 1000,       // ms
        ],
    ],
],
```

## GatewayRule Constructor

```php
use Erikwang2013\IndustrialProtocols\Gateway\GatewayRule;

$rule = new GatewayRule(
    id: 'modbus-to-opcua',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'opcua-server',
    targetPoint: 'ns=1;s=Temperature',
    transform: fn($v) => $v / 10,     // optional value transform
    trigger: 'poll',                   // 'poll' | 'change' | 'cron'
    interval: 1000,                    // ms
    cronExpression: null,              // cron syntax for 'cron' trigger
    failureThreshold: 5,               // circuit breaker trip count
    cooldownSeconds: 30.0,             // circuit breaker cooldown
);
```

All constructor parameters are `readonly` public properties.

## Trigger Modes

| Mode | Behavior |
|------|----------|
| `poll` | Read source every N ms, write to target unconditionally |
| `change` | Write to target only when source value changes from previous read |
| `cron` | Execute on cron schedule (requires `cronExpression`) |

For `change` trigger, the engine tracks the last seen value per rule and skips the write when the value is identical to the previous read.

## Engine Lifecycle

### Instantiation

```php
use Erikwang2013\IndustrialProtocols\Gateway\GatewayEngine;

$engine = new GatewayEngine(
    $kernel->getConnectionManager(),
    $eventDispatcher,
    $kernel->getCoroutineAdapter(),
    $kernel->getLogDriver(),
);
```

### Adding and Removing Rules

```php
$engine->addRule(new GatewayRule(
    id: 'temp-fwd',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'scada',
    targetPoint: 'AI1',
    trigger: 'poll',
    interval: 2000,
));

$engine->removeRule('temp-fwd');
```

### Execution Modes

```php
// Single rule on-demand execution
$result = $engine->executeOnce('temp-fwd');
// Returns: ['status' => 'ok', 'rule' => 'temp-fwd', 'value' => 42, 'latency_ms' => 3.2]

// Tick: execute all poll-triggered rules once
$results = $engine->tick();
// Returns array of results keyed by rule ID

// Continuous loop with tick interval
$engine->run(tickIntervalMs: 100);
```

### Stopping

```php
$engine->stop();  // stops the run() loop
```

## Value Translation Pipeline

Each rule execution follows this pipeline:

```
Source Frame → Parse → Read Value → Transform Callable → Write Target Frame
```

### Transform Examples

```php
// Celsius to Fahrenheit
'transform' => fn($celsius) => $celsius * 9 / 5 + 32,

// Scale raw integer to decimal
'transform' => fn($raw) => $raw / 10,

// Clamp within range
'transform' => fn($v) => min(max($v, 0), 100),

// String formatting
'transform' => fn($v) => number_format($v, 2),
```

Set `transform` to `null` to pass the raw value through unchanged.

## Circuit Breaker

Every rule has an associated circuit breaker that prevents cascading failures when a device is unreachable.

### States

| State | Meaning |
|-------|---------|
| `CLOSED` | Normal operation, requests pass through |
| `OPEN` | Failure threshold exceeded, requests are blocked |
| `HALF_OPEN` | Cooldown period has elapsed, next request probes recovery |

### Default Parameters

- **Failure threshold**: 5 consecutive failures
- **Cooldown period**: 30 seconds
- After cooldown, the breaker transitions to HALF_OPEN; the next execution attempt determines whether it resets to CLOSED (on success) or re-opens (on failure).

### Events

| Event | Fires When |
|-------|------------|
| `GatewayRuleStartedEvent` | Rule execution begins |
| `GatewayRuleCompletedEvent` | Rule execution succeeds (includes latency) |
| `GatewayRuleFailedEvent` | Rule execution fails (includes failure count) |
| `GatewayCircuitBreakerEvent` | Circuit breaker transitions state |

### Monitoring

Repeated circuit breaker trips indicate persistent device issues. You can monitor failures through the event dispatcher:

```php
$eventDispatcher->addListener(
    Erikwang2013\IndustrialProtocols\Event\GatewayCircuitBreakerEvent::class,
    function ($event) {
        // Log, alert, or notify
    }
);
```

## Coroutine Parallelism

In coroutine-supported environments (Swoole, Fiber), all poll rules execute in parallel during a `tick()` call. In synchronous environments, rules execute sequentially.

## Use Case Examples

### Forward Modbus Register to BACnet

```php
$engine->addRule(new GatewayRule(
    id: 'modbus-to-bacnet',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'bacnet-scada',
    targetPoint: '0:1:85',
    transform: fn($v) => (int)($v * 100),
    trigger: 'change',       // only forward on change
));
```

### Periodic Data Logging

```php
$engine->addRule(new GatewayRule(
    id: 'logger',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'logger-db',
    targetPoint: 'temperature',
    trigger: 'poll',
    interval: 60000,  // every 60 seconds
));
```
