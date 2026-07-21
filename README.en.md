# Industrial Protocols PHP

PHP industrial network communication protocol plugin — micro-kernel + protocol SDK architecture supporting Modbus, BACnet, EtherNet/IP and more. Compatible with Plain PHP, Laravel, Webman, Hyperf, ThinkPHP, and Yii2.

> [中文版](README.md)

---

## Table of Contents

- [Design Philosophy](#design-philosophy)
- [Architecture](#architecture)
- [Feature List](#feature-list)
- [Supported Industrial Protocols](#supported-industrial-protocols)
- [Supported Frameworks](#supported-frameworks)
- [Quick Start](#quick-start)
- [Usage Guide](#usage-guide)
- [Protocol Examples](#protocol-examples)
- [Framework Integration Examples](#framework-integration-examples)
- [Gateway Engine](#gateway-engine)
- [Monitoring & Alerting](#monitoring--alerting)
- [Configuration Reference](#configuration-reference)
- [Documentation](#documentation)
- [Requirements](#requirements)
- [License](#license)

---

## Design Philosophy

### Why Micro-Kernel?

Industrial communication protocols are numerous (Modbus, BACnet, OPC UA, Profinet, EtherNet/IP...) and each has multiple variants (TCP/RTU/ASCII, Client/Server). Bundling all protocols into a single package leads to:

- **Bloated packages** — users who only need Modbus must install everything
- **Protocol coupling** — a bug fix in one protocol forces a global release
- **Extension difficulty** — third parties must modify core code to contribute new protocols

The micro-kernel approach splits the system into two layers:

```
┌─────────────────────────────────────────────────┐
│  Protocol Layer (variable)                       │
│  modbus-pkg · bacnet-pkg · ethernetip-pkg · ...  │
│  Each protocol is an independent Composer package │
│  following a unified SDK contract                 │
├─────────────────────────────────────────────────┤
│  Kernel Layer (stable)                           │
│  industrial-protocols-kernel                     │
│  Connection mgmt · Config mgmt · Gateway engine   │
│  Events/Logging/Coroutine · Framework adapters    │
│  SDK interfaces · Monitoring · Alerting           │
└─────────────────────────────────────────────────┘
```

**The kernel only defines "what a protocol is" — it contains zero protocol implementations.** Protocol packages plug in by implementing SDK interfaces, and users install only what they need.

### Protocol SDK Contract

All protocol packages must implement 6 core interfaces:

```php
interface ProtocolInterface    // Identity: name, version, variants, create connector
interface ConnectorInterface   // Device connection: connect/disconnect/read/write/health
interface DriverInterface      // Low-level transport: send frame → receive frame
interface FrameInterface       // Protocol frame: toBytes/fromBytes/getData
interface DataPointInterface   // Data point: address, type, access rights
interface GatewayRuleInterface // Gateway rule: source→target mapping + transform
```

Protocol packages depend only on the kernel. After implementing the interfaces, declare the protocol class via composer.json's `extra` field:

```json
{
    "extra": {
        "industrial-protocols": {
            "protocol": "Erikwang2013\\IndustrialProtocols\\Modbus\\ModbusProtocol"
        }
    }
}
```

The kernel auto-scans installed packages' `extra` fields at boot, discovers and registers protocols — zero configuration for users.

### Framework Adapter Strategy

Every PHP framework has different service containers, config loading, and CLI mechanisms. The kernel abstracts this with `FrameworkAdapterInterface`:

```php
interface FrameworkAdapterInterface
{
    public function detect(): bool;               // Is this framework present?
    public function getName(): string;            // Framework name
    public function registerConfig(): void;       // Register/publish config
    public function registerServices(): void;     // Register container bindings
    public function registerCommands(): void;     // Register CLI commands
    public function getConfigPath(): string;      // Config file path
    public function isLongRunning(): bool;        // Persistent process?
}
```

At boot, the kernel iterates adapters in priority order. The first adapter whose `detect()` returns `true` wins. Falls back to `PlainPhpAdapter` if no framework is detected.

### Unified Coroutine Abstraction

PHP has multiple coroutine runtimes (Swoole, Swow, Fiber) with different APIs. The kernel provides a unified `CoroutineAdapterInterface`:

```php
interface CoroutineAdapterInterface
{
    public function isAvailable(): bool;
    public function create(callable $fn): mixed;        // Create coroutine
    public function sleep(float $seconds): void;        // Coroutine sleep
    public function parallel(array $callables): array;  // Concurrent execution
}
```

Detection priority: `Swoole → Swow → Fiber → Sync`. Higher-level components (ConnectionManager, GatewayEngine) use this interface for runtime-agnostic logic.

---

## Architecture

### System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      User Application                        │
├─────────────────────────────────────────────────────────────┤
│              Framework Adapters (auto-discovery)             │
│    Laravel  │  Webman  │  Hyperf  │  ThinkPHP  │  Yii2      │
│    ServiceProvider  config/plugin  ConfigProvider  ...      │
├─────────────────────────────────────────────────────────────┤
│                     Micro-Kernel                             │
│  ┌──────────┬──────────┬──────────┬──────────┬───────────┐  │
│  │ Protocol │Connection│  Config  │ Gateway  │  Logging  │  │
│  │ Registry │ Manager  │Repository│ Engine   │  /Event   │  │
│  ├──────────┼──────────┼──────────┼──────────┼───────────┤  │
│  │Coroutine │  Retry   │  Alert   │ Metrics  │ Security  │  │
│  │ Adapter  │ Strategy │ Manager  │Collector │ Validator │  │
│  └──────────┴──────────┴──────────┴──────────┴───────────┘  │
├─────────────────────────────────────────────────────────────┤
│                   Protocol SDK (Contracts)                   │
│  ProtocolInterface │ ConnectorInterface │ DriverInterface    │
│  FrameInterface    │ DataPointInterface │ GatewayRuleInterface│
├─────────────────────────────────────────────────────────────┤
│              Protocol Packages (SDK Implementations)          │
│  Modbus    │  BACnet/IP   │  EtherNet/IP   │  (OPC UA ...)  │
│  pure PHP  │  UDP socket  │  TCP ENIP+CIP  │  (planned)     │
└─────────────────────────────────────────────────────────────┘
```

### Package Dependencies (one-way)

```
  protocol-modbus ──┐
  protocol-bacnet ──┼──→ industrial-protocols-kernel
  protocol-eip ────┘              ↑
                    user-app ─────┘
```

Users only need `composer require industrial-protocols/kernel industrial-protocols/modbus` — the kernel auto-discovers installed protocol packages.

### Connection Lifecycle

```
                    ┌──────────────┐
        connect() → │  CONNECTING  │ → (fail) → FAULT → retry? → CONNECTING
                    └──────┬───────┘
                           ↓ (success)
                    ┌──────────────┐
                    │   CONNECTED  │ ← → disconnect()
                    └──────┬───────┘
                           ↓ (error detected)
                    ┌──────────────┐
                    │   DEGRADED   │ → (recover) → CONNECTED
                    └──────┬───────┘    (fail)    → FAULT
                           ↓
                    ┌──────────────┐
                    │    FAULT     │ → retry → CONNECTING
                    └──────┬───────┘
                           ↓ (max retries exceeded)
                    ┌──────────────┐
                    │    CLOSED    │
                    └──────────────┘
```

### Gateway Engine Data Flow

```
  Source Device                Target Device
       │                            │
  ┌────▼────┐                  ┌────▼────┐
  │ Modbus  │                  │ OPC UA  │
  │ PLC     │                  │ Server  │
  └────┬────┘                  └────▲────┘
       │                            │
  ┌────▼────────────────────────────▲────┐
  │           Gateway Engine              │
  │                                       │
  │  Rule Loader → Rule Scheduler         │
  │       │            │                  │
  │       ▼            ▼                  │
  │  ┌─────────────────────┐              │
  │  │  Transform Pipeline  │              │
  │  │  Read → Cast → Map  │              │
  │  │  → Transform → Write│              │
  │  └─────────────────────┘              │
  │       │                               │
  │  ┌────▼────┐  ┌────────────┐          │
  │  │ Metrics │  │  Circuit   │          │
  │  │Collector│  │  Breaker   │          │
  │  └─────────┘  └────────────┘          │
  └───────────────────────────────────────┘
```

### Data Transform Pipeline

```
Source Frame (raw bytes)
  → Driver::send(read_frame)
    → Frame::fromBytes(response)
      → Frame::getData()              // Extract structured data
        → Transform callable?          // Optional custom transform
          → Target Frame::toBytes()    // Build target protocol frame
            → Target Driver::send()    // Write to target device
```

### Exception Hierarchy

```
IndustrialProtocolsException (RuntimeException)
├── ConnectionException
│   ├── ConnectionTimeoutException     — TCP connection timeout
│   ├── ConnectionRefusedException     — Connection refused
│   └── ConnectionClosedException      — Connection closed
├── ProtocolException
│   ├── FrameException                  — Illegal frame format
│   └── CrcException                    — Checksum mismatch
├── DeviceException
│   ├── DeviceBusyException             — Device busy
│   └── AddressOutOfRangeException      — Address out of range
└── GatewayException
    ├── RuleValidationException         — Rule validation failed
    └── CircuitBreakerOpenException     — Circuit breaker open
```

---

## Feature List

### Kernel

| Feature | Description |
|---------|-------------|
| SDK Interfaces | 6 standard interfaces. Third parties can develop new protocol packages against the SDK. |
| Protocol Registry | Auto-scans Composer-installed protocol packages, zero-config loading. |
| Connection Manager | 3 strategies — Lazy, Eager, Pooled. Health checks and auto-reconnection included. |
| Config Management | FileConfigRepository / DatabaseConfigRepository / EnvConfigRepository. |
| Coroutine Adaptation | Swoole → Fiber → Sync three-tier auto-degradation. |
| Event System | 13 event types based on PSR-14. Custom listeners supported. |
| Log Drivers | PsrLogDriver / FileLogDriver / NullLogDriver. |
| Retry Strategies | NoRetry / FixedRetry / ExponentialBackoff / Jittered. |
| Exception Hierarchy | 20+ layered exceptions with context. |
| Framework Adapters | 6 frameworks + plain PHP, auto-detected at boot. |

### Gateway Engine

| Feature | Description |
|---------|-------------|
| Rule Engine | poll / change / cron trigger modes. |
| Data Pipeline | Source → Parse → Transform → Encode → Target. |
| Circuit Breaker | CLOSED → OPEN → HALF_OPEN state machine. |
| Concurrent Execution | Rules execute in parallel in coroutine environments. |

### Monitoring & Security

| Feature | Description |
|---------|-------------|
| Metrics | Counter / Gauge / Histogram with Prometheus export. |
| Alert Channels | AlertManager + Webhook / Log channels. |
| Input Validation | Device ID, host, port, register address, frame size, timeout. |

---

## Supported Industrial Protocols

| Protocol | Phase | Variants | Default Port | Implementation | Supported Operations |
|----------|-------|----------|-------------|----------------|---------------------|
| **Modbus** | Phase 1 | TCP, RTU, ASCII | 502 | Pure PHP Socket | FC 01/03/04/06/10 |
| **BACnet/IP** | Phase 3 | IP (UDP) | 47808 | Pure PHP UDP Socket | Who-Is/I-Am, ReadProperty |
| **EtherNet/IP** | Phase 3 | TCP | 44818 | Pure PHP Socket | ENIP session, CIP Read Tag |
| **OPC UA** | Planned | Binary | 4840 | FFI / C bridge | — |
| **Profinet** | Planned | NRT + RT | 34964 | FFI / C library bridge | — |

---

## Supported Frameworks

| Framework | Phase | Detection | Coroutine | Integration |
|-----------|-------|-----------|-----------|-------------|
| **Plain PHP** | Phase 1 | Fallback | Fiber | Direct Kernel instantiation |
| **Laravel** | Phase 2 | Application class | Octane (Swoole) | ServiceProvider + Facade + artisan |
| **Webman** | Phase 2 | Worker class | Swoole/Fiber | config/plugin auto-discovery |
| **Hyperf** | Phase 3 | ApplicationFactory | Swoole native | ConfigProvider + DI container |
| **ThinkPHP** | Phase 3 | think\App | think-swoole | services.php + singleton |
| **Yii2** | Phase 3 | yii\base\Application | swoole-yii2 | Bootstrap + component |

Detection priority: `Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Plain PHP`

---

## Quick Start

```bash
composer require industrial-protocols/kernel industrial-protocols/modbus
```

```php
<?php
require 'vendor/autoload.php';

use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

// 1. Create config file
$config = __DIR__ . '/industrial-protocols.php';
file_put_contents($config, '<?php return ' . var_export([
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus', 'variant' => 'tcp',
            'host' => '192.168.1.10', 'port' => 502,
            'unit_id' => 1, 'timeout' => 3000,
        ],
    ],
    'gateway' => ['rules' => []],
    'health_check_interval' => 30,
], true) . ';');

// 2. Boot kernel
$kernel = new Kernel(['config_path' => $config]);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

// 3. Read data
$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
echo "Temperature: " . $result['40001'] . "\n";

// 4. Write data
$conn->write(['40001' => 25]);

// 5. Health check
$health = $kernel->getConnectionManager()->health('plc-001');
echo "State: {$health->state->value}, Latency: {$health->latencyMs}ms\n";

$kernel->shutdown();
```

---

## Usage Guide

### ConnectionManager API

```php
$manager = $kernel->getConnectionManager();

$conn = $manager->connect('plc-001');       // Connect
$existing = $manager->getConnection('plc-001'); // Get existing
$manager->disconnect('plc-001');            // Disconnect
$all = $manager->getAllConnections();       // All active connections
$health = $manager->health('plc-001');       // Single health check
$allHealth = $manager->healthAll();          // All health checks
```

### Connection Strategies

- **LAZY** (default) — Connect on first read/write. Best for FPM.
- **EAGER** — Connect all at boot. Best for persistent processes.
- **POOLED** — Pre-built connection pool with round-robin. Best for high-frequency polling.

### Retry Configuration

```php
use Erikwang2013\IndustrialProtocols\Retry\ExponentialBackoffStrategy;

$strategy = new ExponentialBackoffStrategy(
    maxAttempts: 5, baseDelayMs: 1000,
    jitter: true,  // Random jitter to prevent thundering herd
);
```

### Event Listening

```php
use Erikwang2013\IndustrialProtocols\Event\DataReadEvent;
use Erikwang2013\IndustrialProtocols\Event\ConnectionStateChangedEvent;

$dispatcher->listen(DataReadEvent::class, function (DataReadEvent $e) {
    echo "Device {$e->deviceId} read complete, {$e->latencyMs}ms\n";
});
$dispatcher->listen(ConnectionStateChangedEvent::class, function ($e) {
    if ($e->newStatus->state->value === 'FAULT') { /* trigger alert */ }
});
```

---

## Protocol Examples

### Modbus TCP

```php
$conn->read('40001');                        // Single register → ['40001' => 237]
$conn->read(['40001', '40002']);             // Batch read
$conn->write(['40001' => 100]);              // Single write
$conn->write(['40001' => 200, '40002' => 300]); // Batch write
// 40001-49999 = Holding Register, 30001-39999 = Input Register, 0-9999 = raw offset
```

### BACnet/IP

```php
$devices = $conn->discoverDevices(5);        // Who-Is broadcast, 5s timeout
$result = $conn->read('0:1:85');             // AnalogInput 1, PresentValue
```

### EtherNet/IP

```php
$result = $conn->read('MyTagName');          // CIP Read Tag
```

---

## Framework Integration Examples

### Laravel

```bash
php artisan vendor:publish --tag=industrial-protocols-config
php artisan industrial:connect plc-001
php artisan industrial:gateway:list
```

```php
use Erikwang2013\IndustrialProtocols\Framework\Laravel\IndustrialProtocolsFacade;
$result = IndustrialProtocolsFacade::connect('plc-001')->read('40001');
```

### Webman

Create `config/plugin/industrial-protocols/kernel/config/industrial-protocols.php`. ProtocolProcess auto-boots on worker start. No extra code needed.

### Hyperf

Uses ConfigProvider for DI auto-injection. Create `config/autoload/industrial-protocols.php`.

```php
$kernel = ApplicationContext::getContainer()->get(Kernel::class);
```

### ThinkPHP

```php
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;
$kernel = IndustrialProtocolsService::boot();
```

### Yii2

```php
// config/web.php: add Bootstrap and component
$kernel = Yii::$app->get('industrial-protocols');
```

---

## Gateway Engine

```php
use Erikwang2013\IndustrialProtocols\Gateway\GatewayEngine;
use Erikwang2013\IndustrialProtocols\Gateway\GatewayRule;

$engine = new GatewayEngine(/* ... */);

$engine->addRule(new GatewayRule(
    id: 'modbus-to-opcua',
    sourceDevice: 'plc-001', sourcePoint: '40001',
    targetDevice: 'opcua-server', targetPoint: 'ns=1;s=Temperature',
    transform: fn($raw) => $raw / 10,
    trigger: 'poll', interval: 1000,
));

$engine->executeOnce('modbus-to-opcua');     // Run once
$engine->run(tickIntervalMs: 100);           // Continuous loop
```

Trigger modes: `poll` (periodic), `change` (value-triggered), `cron` (schedule-based).

---

## Monitoring & Alerting

```php
use Erikwang2013\IndustrialProtocols\Metrics\MetricsCollector;
use Erikwang2013\IndustrialProtocols\Alert\AlertManager;
use Erikwang2013\IndustrialProtocols\Alert\WebhookAlertChannel;

$metrics = new MetricsCollector();
$metrics->incrementCounter('reads_total', ['device' => 'plc-001']);
$metrics->observeHistogram('read_latency_ms', 15.2);
echo $metrics->toPrometheus('industrial');   // Prometheus text format

$alert = new AlertManager();
$alert->addChannel('webhook', new WebhookAlertChannel('https://...'));
$alert->send('Device Down', 'plc-001 timeout', level: 'critical');
```

---

## Configuration Reference

```php
<?php
// industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol'  => 'modbus',        // Protocol name
            'variant'   => 'tcp',           // Variant
            'host'      => '192.168.1.10',  // IP or serial
            'port'      => 502,             // Port
            'unit_id'   => 1,               // Slave ID
            'timeout'   => 3000,            // Timeout (ms)
            'strategy'  => 'lazy',          // lazy | eager | pooled
            'points'    => [                // Data point mappings
                ['address' => '40001', 'name' => 'temperature', 'type' => 'FLOAT32', 'access' => 'RW'],
            ],
        ],
    ],
    'gateway' => [
        'rules' => [[
            'id' => 'gw-001', 'source_device' => 'plc-001',
            'source_point' => '40001', 'target_device' => 'opcua-server',
            'target_point' => 'ns=1;s=Temperature',
            'trigger' => 'poll', 'interval' => 1000,
        ]],
    ],
    'health_check_interval' => 30,
    'default_retry_max'     => 3,
    'default_retry_backoff' => 'exponential',
    'default_timeout'       => 3000,
];
```

---

## Documentation

- [Protocol API Reference](docs/en/protocols.md)
- [Framework Integration Guide](docs/en/framework-integration.md)
- [Gateway Engine Guide](docs/en/gateway.md)
- [Security Guide](docs/en/security.md)

---

## Requirements

- PHP >= 8.1
- Composer
- Optional: ext-swoole (Swoole coroutine acceleration)
- Optional: ext-pdo (database config storage)

---

## License

MIT
