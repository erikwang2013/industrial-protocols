# Industrial Protocols PHP Plugin — Design Spec
> [中文](2026-07-21-industrial-protocols-design.md)

**Date:** 2026-07-21  
**Status:** In Progress  
**Author:** Erik

---

## 1. Requirements Summary

| # | Question | Decision |
|---|----------|----------|
| 1 | Protocol scope | All major protocols: Modbus, Profinet, EtherNet/IP, OPC UA, BACnet, etc. |
| 2 | Core use cases | Data acquisition + device control + protocol gateway/conversion |
| 3 | Async support | Sync-first; framework adapters handle coroutine/fiber adaptation |
| 4 | PHP version | ≥ 8.1 (Fiber, enums, readonly properties) |
| 5 | Protocol implementation | Layered strategy: simple protocols (Modbus, BACnet) pure PHP socket; complex protocols (OPC UA, EtherNet/IP, Profinet) FFI or bridge |
| 6 | Framework integration | Single package auto-discovery: detect runtime environment, plug and play |
| 7 | Config management | File-based default + Repository interface for database-backed config; simple setups use files, complex setups use DB |
| 8 | Testing strategy | TDD first (protocol simulation tests ≥80% coverage), scale to full E2E |
| 9 | Architecture pattern | Micro-kernel + Protocol SDK (Approach C) |

---

## 2. Overall Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      User Application                        │
├─────────────────────────────────────────────────────────────┤
│              Framework Adapters (auto-discovery)             │
│         Laravel  │  Webman  │  Hyperf  │  ThinkPHP  │  Yii  │
├─────────────────────────────────────────────────────────────┤
│                     Micro-Kernel (Core)                      │
│  ┌──────────┬──────────┬──────────┬──────────┬───────────┐  │
│  │ Protocol │Connection│  Config  │ Gateway  │  Logging  │  │
│  │ Registry │  Manager │ Repository│ Engine  │  /Event   │  │
│  └──────────┴──────────┴──────────┴──────────┴───────────┘  │
├─────────────────────────────────────────────────────────────┤
│                   Protocol SDK (Contracts)                   │
│  ProtocolInterface │ ConnectorInterface │ DriverInterface    │
│  DataPointInterface│ GatewayRuleInterface│ HealthCheck       │
├─────────────────────────────────────────────────────────────┤
│              Protocol Packages (SDK Implementations)          │
│   modbus-pkg   │  opcua-pkg  │  profinet-pkg  │  bacnet-pkg │
│   ethernetip-pkg  │  ...  │   (3rd-party community pkgs)    │
└─────────────────────────────────────────────────────────────┘
```

### Package Dependency (one-way)

```
protocol-modbus ──┐
protocol-opcua ───┼──→ industrial-protocols-kernel
protocol-bacnet ──┘              ↑
                  user-app ──────┘
```

---

## 3. Core Principles

1. **Kernel (`industrial-protocols-kernel`)** — Thin contract layer + service container. Contains zero protocol implementations. Defines what a protocol is, how it registers, how it's discovered, how it's configured.

2. **Protocol SDK** — Set of PHP Interfaces. Every protocol package MUST implement them. This is the single contract between kernel and protocol implementations.

3. **Framework Adapters** — Built into the kernel. Detect runtime environment via Composer's `installed.json`, auto-register ServiceProvider / ConfigProvider / ConfigPlugin.

4. **Protocol Packages** — Independent composer packages depending on `industrial-protocols-kernel`. Implement SDK interfaces, auto-registered via Protocol Registry.

5. **Gateway Engine** — Built into the kernel. Protocol-agnostic rule-chain-based conversion engine. Input/output decoupled through SDK interfaces.

6. **User install:** `composer require industrial-protocols-kernel industrial-protocols-modbus` — kernel discovers installed protocol packages via Registry and auto-registers them.

---

## 4. Protocol SDK Contracts

### Core Interfaces

```php
// Protocol identity — describes what a protocol is
interface ProtocolInterface
{
    public function getName(): string;           // e.g. 'modbus'
    public function getVersion(): string;        // e.g. '1.0.0'
    public function getSupportedVariants(): array; // e.g. ['tcp', 'rtu', 'ascii']
    public function getDefaultPort(): int;
    public function createConnector(array $config): ConnectorInterface;
}

// Connector — a concrete device connection
interface ConnectorInterface
{
    public function connect(): void;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function read(string|array $points): array;
    public function write(string|array $points, array $values): array;
    public function getHealth(): HealthStatus;
}

// Driver — low-level transport (pure PHP socket / FFI / bridge)
interface DriverInterface
{
    public function send(FrameInterface $frame): FrameInterface;
    public function getLatency(): float;
    public function supportsAsync(): bool;
}

// Frame — protocol data unit
interface FrameInterface
{
    public function toBytes(): string;
    public static function fromBytes(string $bytes): static;
    public function getData(): array;
}

// DataPoint — a readable/writable point
interface DataPointInterface
{
    public function getAddress(): string;   // e.g. '40001', 'DB1.DBX0.0'
    public function getType(): DataType;    // enum: INT16, UINT16, FLOAT32, BOOL, STRING...
    public function getAccess(): Access;    // enum: READ, WRITE, READ_WRITE
}
```

### Gateway Interface

```php
// Gateway Rule
interface GatewayRuleInterface
{
    public function getSource(): ConnectorInterface;
    public function getTarget(): ConnectorInterface;
    public function getMapping(): array;       // source_address => target_address
    public function getTransform(): ?callable; // optional data transform
    public function getInterval(): int;        // sync interval (ms)
}
```

### Kernel Components

| Component | Role |
|-----------|------|
| `ProtocolRegistry` | Discover installed protocol packages, manage ProtocolInterface instances |
| `ConnectionManager` | Create/cache/destroy ConnectorInterface instances per protocol+config |
| `ConfigRepository` | Unified config read/write interface, file-based default, DB-swappable |

---

## 5. Connection Manager

### API

```
ConnectionManager
├── connect(deviceId): ConnectorInterface
├── disconnect(deviceId): void
├── getConnection(deviceId): ?ConnectorInterface
├── getAllConnections(): ConnectorInterface[]
├── health(deviceId): HealthStatus
└── healthAll(): HealthStatus[]
```

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

### Connection Strategies

| Strategy | Behavior | Use Case |
|----------|----------|----------|
| `EAGER` | Establish all connections on startup | Few devices, latency-sensitive |
| `LAZY` (default) | Connect on first read/write | Many devices, intermittent access |
| `POOLED` | Pre-build connection pool, reuse connections | High-frequency polling, gateway |

### Health Check

- Each `ConnectorInterface` implements its own `getHealth()`
- `ConnectionManager` polls all connections per configurable interval (default 30s)
- `HealthStatus` carries: state enum (`HEALTHY / DEGRADED / FAULT`), latency, last error, retry count
- State changes fire `ConnectionStateChangedEvent`

### Reconnection

- Configurable: max retries (default 3), retry interval (default 1s), backoff (fixed / exponential)
- Reconnect success: `DEGRADED → CONNECTED`, fires recovery event
- Retries exhausted: `FAULT → CLOSED`, fires alert event

---

## 6. Config Repository

### Two-layer Model

```
Layer 1: Device Connection Config (must persist)
  Protocol type, IP/serial, port, slave ID, timeout, retry params...
  → File (simple) or database (complex)

Layer 2: Data Point Mapping (dynamic)
  Register address, tag name, data type, access, transform...
  → Same Repository interface
```

### Interface

```php
interface ConfigRepositoryInterface
{
    // Device config
    public function getDeviceConfig(string $deviceId): array;
    public function setDeviceConfig(string $deviceId, array $config): void;
    public function removeDeviceConfig(string $deviceId): void;
    public function getAllDeviceConfigs(): array;

    // Data point mapping
    public function getDataPoints(string $deviceId): array;
    public function setDataPoints(string $deviceId, array $points): void;

    // Gateway rules
    public function getGatewayRules(): array;
    public function addGatewayRule(array $rule): void;
    public function removeGatewayRule(string $ruleId): void;
}
```

### Built-in Implementations

| Implementation | Storage | Use Case |
|---------------|---------|----------|
| `FileConfigRepository` (default) | PHP/JSON/YAML | ≤10 devices, simple deploy |
| `DatabaseConfigRepository` | MySQL/SQLite/PG | Many devices, runtime management |
| `EnvConfigRepository` | Environment variables | Docker/K8s containerized |

### Config File Example

```php
// config/industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'variant'  => 'tcp',
            'host'     => env('PLC_001_HOST', '192.168.1.10'),
            'port'     => 502,
            'unit_id'  => 1,
            'timeout'  => 3000,
            'strategy' => 'lazy',
            'points'   => [
                ['address' => '40001', 'name' => 'temperature', 'type' => 'FLOAT32', 'access' => 'RW'],
                ['address' => '40003', 'name' => 'pressure',    'type' => 'FLOAT32', 'access' => 'RO'],
            ],
        ],
    ],
    'gateway' => [
        'rules' => [
            ['id' => 'gw-001', 'source_device' => 'plc-001', 'source_point' => '40001',
             'target_device' => 'opcua-server', 'target_tag' => 'ns=1;s=Temperature',
             'interval' => 1000],
        ],
    ],
    'health_check_interval' => 30,
    'default_retry_max' => 3,
    'default_retry_backoff' => 'exponential',
];
```

### Repository Switching

Kernel detects at boot: if user injected `DatabaseConfigRepository`, use database. Otherwise use `FileConfigRepository`. Switching is transparent — framework adapter binds the concrete implementation in ServiceProvider.

---

## 7. Gateway Engine

### Architecture

```
┌──────────────────────────────────────────────────────────┐
│                     Gateway Engine                        │
│                                                          │
│  ┌─────────────┐   ┌──────────────┐   ┌─────────────┐   │
│  │ Rule Loader │ → │ Rule Scheduler│ → │ Rule Runner │   │
│  │ (from config│   │ (per-rule     │   │ (read source │   │
│  │  or DB)     │   │  interval)    │   │  → transform │   │
│  └─────────────┘   └──────────────┘   │  → write tgt)│   │
│                                       └──────┬──────┘   │
│                                              ↓           │
│  ┌──────────────────────────────────────────────────┐    │
│  │              Data Transform Pipeline              │    │
│  │  Raw Bytes → Parse → Normalize → Map → Encode    │    │
│  └──────────────────────────────────────────────────┘    │
│                                                          │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────┐     │
│  │ Metrics  │  │ Circuit  │  │ Rule Validation     │     │
│  │ Collector│  │ Breaker  │  │ (type compatibility)│     │
│  └──────────┘  └──────────┘  └────────────────────┘     │
└──────────────────────────────────────────────────────────┘
```

### Rule Model

```php
$rule = [
    'id'           => 'gw-001',
    'source'       => ['device' => 'plc-001', 'point' => '40001'],
    'target'       => ['device' => 'opcua-server', 'point' => 'ns=1;s=Temperature'],
    'transform'    => null,         // null = passthrough, callable = custom
    'type_coerce'  => true,         // FLOAT32 → Double auto-convert
    'interval'     => 1000,         // ms, 0 = event-driven
    'trigger'      => 'poll',       // 'poll' | 'change' | 'cron'
    'circuit'      => ['max_failures' => 5, 'cooldown' => 30],
];
```

### Trigger Modes

| Mode | Behavior |
|------|----------|
| `poll` | Fixed interval: read source → write target |
| `change` | Write only when source data changes (event-driven) |
| `cron` | Cron expression (e.g. `*/5 * * * *`) batch sync |

### Data Transform Pipeline

```
Source Frame
  → Driver::send(read_frame)
    → Frame::getData()              // ['40001' => 23.5]
      → Transform callable?          // optional custom transform
        → Type coercion              // FLOAT32 → Double
          → Target Frame::fromData(['ns=1;s=Temperature' => 23.5])
            → Target Driver::send(write_frame)
```

Null `transform` with compatible types = direct passthrough. Type coercion handles cross-type mapping (INT16 → FLOAT32, etc.). Complex transforms (linear scaling, byte-order swap, multi-register assembly) use user-provided callable.

### Circuit Breaker

Single rule with N consecutive failures auto-pauses. After cooldown, half-open retry. Prevents cascading failure across rules.

---

## 8. Framework Adapters + Unified Coroutine Layer

All frameworks support at least one coroutine runtime. Coroutine capability is handled at the Kernel level, not per-framework.

### Coroutine Adapter (Kernel Built-in)

```php
interface CoroutineAdapterInterface
{
    public function isAvailable(): bool;
    public function create(callable $fn): mixed;
    public function sleep(float $seconds): void;
    public function parallel(array $callables): array;
    public function channel(int $capacity = 0): ChannelInterface;
}
```

| Implementation | Runtime | Detection |
|---------------|---------|------------|
| `SwooleCoroutineAdapter` | Swoole | `extension_loaded('swoole') && Co::getCid() >= 0` |
| `SwowCoroutineAdapter` | Swow | `extension_loaded('swow')` |
| `FiberCoroutineAdapter` | PHP 8.1 Fiber | Pure PHP, no extension needed |
| `SyncCoroutineAdapter` (fallback) | None | Synchronous blocking |

Detection priority: `Swoole → Swow → Fiber → Sync`

### Framework Coroutine Support Matrix

| Framework | Swoole | Swow | Fiber | FPM |
|-----------|--------|------|-------|-----|
| **Laravel** | Octane (Swoole) | — | Octane (Fiber) | default |
| **Webman** | Swoole event driver | — | workerman 5.x | workerman 4.x |
| **Hyperf** | native | native | — | — |
| **ThinkPHP** | think-swoole | — | — | default |
| **Yii2** | swoole-yii2 | — | — | default |

### Framework Adapter (simplified)

```php
interface FrameworkAdapterInterface
{
    public function detect(): bool;
    public function getName(): string;              // 'laravel', 'webman', 'hyperf'...
    public function registerConfig(): void;
    public function registerServices(): void;
    public function registerCommands(): void;
    public function getConfigPath(): string;
    public function isLongRunning(): bool;          // persistent process?
}
```

Framework adapter responsibilities (shrunk to): **config publish + container bindings + CLI commands**. Coroutine, connection pool, async — all handled by Kernel's `CoroutineAdapterInterface`.

### Per-Framework Adapter Behavior

**LaravelAdapter:**
```php
// 1. Register ServiceProvider → bind ConfigRepository, ConnectionManager to container
// 2. publishConfig() → copy config/industrial-protocols.php to app config/
// 3. Register artisan commands: php artisan industrial:connect, industrial:gateway:list
// 4. Register Facade: IndustrialProtocols::connect('plc-001')->read('40001')
```

**WebmanAdapter:**
```php
// 1. Use config/plugin/ auto-load — config files under package
//    config/plugin/industrial-protocols/ auto-merge into global config
// 2. Install-and-use, no publish step needed
// 3. Init ConnectionManager at worker start via process model
```

**HyperfAdapter:**
```php
// 1. ConfigProvider declares all DI bindings and annotations
// 2. config/autoload/industrial-protocols.php auto-merged
// 3. Leverage Hyperf coroutine + pool for POOLED strategy
// 4. Register hyperf CLI commands
```

**ThinkPHPAdapter / Yii2Adapter:**
```php
// Use respective service registration mechanisms
// Pure FPM → ConnectionManager defaults to LAZY strategy
```

### Fallback: PlainPhpAdapter

```php
// No-framework fallback
$kernel = new Erikwang2013\IndustrialProtocols\Kernel([
    'config_path' => __DIR__ . '/industrial-protocols.php',
]);
$kernel->boot();
$conn = $kernel->getConnectionManager()->connect('plc-001');
$temp = $conn->read('40001');
```

### Auto-Discovery Flow

```
composer require industrial-protocols-kernel industrial-protocols-modbus
              │
              ▼
┌─────────────────────────────────────────────┐
│  Kernel::boot() iterates FrameworkAdapter[] │
│                                             │
│  LaravelAdapter::detect()                   │
│    → class_exists('Illuminate\...\Application') ? │
│    → true → register, break                 │
│    → false ↓                                │
│  WebmanAdapter::detect()                    │
│    → class_exists('Workerman\Worker') ?     │
│    → true → register, break                 │
│    → ...                                    │
│  (none matched)                             │
│    → PlainPhpAdapter (fallback)             │
└─────────────────────────────────────────────┘
```

### Coroutine Impact on Other Components

**ConnectionManager:**
```php
if ($coroutine->isAvailable()) {
    $strategy = new PooledStrategy($coroutine);  // coroutine-safe pool
} else {
    $strategy = new LazyStrategy();              // FPM: create per request
}
```

**Gateway Engine:**
```php
// poll mode: multiple rules execute concurrently via CoroutineAdapter::parallel()
$coroutine->parallel([
    fn() => $this->runRule('gw-001'),
    fn() => $this->runRule('gw-002'),
    fn() => $this->runRule('gw-003'),
]);
```

**DriverInterface extended:**
```php
interface DriverInterface
{
    public function send(FrameInterface $frame): FrameInterface;
    public function sendAsync(FrameInterface $frame): mixed;  // Promise/coroutine
    public function getLatency(): float;
    public function supportsAsync(): bool;
}
```

---

## 9. Logging & Events

Based on PSR-14 EventDispatcher. All key nodes fire events.

### Event Catalog

```
Connection Events:
  ConnectionConnectedEvent       // device connected
  ConnectionDisconnectedEvent    // device disconnected
  ConnectionStateChangedEvent    // state change (HEALTHY → DEGRADED → FAULT → CLOSED)
  ConnectionRetryEvent           // reconnection attempt

Data Events:
  DataReadEvent                  // read completed (device, point, value, latency)
  DataWriteEvent                 // write completed
  DataErrorEvent                 // read/write error (device, point, error, retry count)

Gateway Events:
  GatewayRuleStartedEvent        // rule execution started
  GatewayRuleCompletedEvent      // rule execution done (source → target rows)
  GatewayRuleFailedEvent         // rule execution failed
  GatewayCircuitBreakerEvent     // breaker opened / reset

System Events:
  KernelBootedEvent              // Kernel boot complete
  ProtocolRegisteredEvent        // protocol discovered by Registry
```

### Log Driver

```php
interface LogDriverInterface
{
    public function log(string $level, string $message, array $context = []): void;
    public function event(object $event): void;
}
```

| Implementation | Behavior |
|---------------|----------|
| `PsrLogDriver` (default) | Delegates to PSR-3 Logger, compatible with all frameworks |
| `NullLogDriver` | Silently drop all logs |
| `FileLogDriver` | Write directly to file, no framework dependency |

### Log Level Convention

| Scenario | Level | Example |
|----------|-------|---------|
| Connect/disconnect | INFO | `Device plc-001 connected (Modbus TCP 192.168.1.10:502)` |
| Read/write | DEBUG | `Read 40001-40010 from plc-001 (23ms)` |
| Reconnecting | WARNING | `Reconnecting plc-001, attempt 2/3` |
| Read/write failure | ERROR | `Write 40001 failed: timeout after 3000ms` |
| Circuit breaker | CRITICAL | `Gateway rule gw-001 breaker opened after 5 failures` |

### Event-Driven Flow

```
ConnectionManager
    │
    ├── connect() ──→ ConnectionConnectedEvent
    │                     │
    │                     ├──→ LogDriver::event()  (write log)
    │                     ├──→ UserListener        (custom callback)
    │                     └──→ AlertChannel        (dingtalk/email/webhook)
    │
    ├── read() ──→ DataReadEvent
    │                 │
    │                 ├──→ MetricsCollector::record()
    │                 └──→ GatewayEngine check change-triggered rules
    │
    └── health() ──→ ConnectionStateChangedEvent
                        │
                        └──→ AlertChannel (if FAULT state)
```

Users register listeners via PSR-14 ListenerProvider. Kernel ships with `EventLoggerSubscriber` that turns all events into logs.

---

## 10. Error Handling & Retry Strategy

### Exception Hierarchy

```
IndustrialProtocolsException (RuntimeException)
├── ConnectionException
│   ├── ConnectionTimeoutException
│   ├── ConnectionRefusedException
│   ├── ConnectionClosedException
│   └── AuthenticationException
├── ProtocolException
│   ├── FrameException              // illegal frame format
│   ├── CrcException                // checksum mismatch
│   ├── UnsupportedVariantException
│   └── DataTypeException
├── DeviceException
│   ├── DeviceBusyException
│   ├── DeviceErrorException        // error code from device
│   └── AddressOutOfRangeException
└── GatewayException
    ├── RuleValidationException
    └── CircuitBreakerOpenException
```

### Retry Strategy

```php
interface RetryStrategyInterface
{
    public function shouldRetry(int $attempt, \Throwable $error): bool;
    public function getDelay(int $attempt): int;  // ms
}
```

| Strategy | Behavior | Use |
|----------|----------|-----|
| `NoRetryStrategy` | No retry | Writes (by default, idempotency risk) |
| `FixedRetryStrategy` | Fixed interval, N times | Simple scenarios |
| `ExponentialBackoffStrategy` (default) | 1s → 2s → 4s → 8s... | Reads, connection setup |
| `JitteredBackoffStrategy` | Exponential + random jitter | Multi-device, prevent thundering herd |

### Layered Retry

```
Layer 1: Driver
  TCP timeout → retry immediately (max 1) → fail → throw ConnectionException

Layer 2: Connector
  ConnectionException → RetryStrategy::shouldRetry() → delay → retry
  Read error → if DEGRADED → trigger health check → may degrade or close

Layer 3: Gateway
  Rule failure → record → trigger CircuitBreaker
  N consecutive → open → cooldown → half-open retry
```

### Error vs Exception Map

| Scenario | Exception | Retry? | Event |
|----------|-----------|--------|-------|
| TCP connect timeout | `ConnectionTimeoutException` | Yes (max 3) | `ConnectionRetryEvent` |
| Frame CRC error | `CrcException` | Yes (max 1) | `DataErrorEvent` |
| Address out of range | `AddressOutOfRangeException` | No | `DataErrorEvent` |
| Device busy | `DeviceBusyException` | Yes (with delay) | `DataErrorEvent` |
| Write failure | `ProtocolException` | No (default) | `DataErrorEvent` |
| Circuit breaker open | `CircuitBreakerOpenException` | No | `GatewayCircuitBreakerEvent` |

### Error Delivery

Two channels simultaneously:

1. **Exception** — synchronous throw, caller can `try/catch`
2. **Event** — `DataErrorEvent` / `ConnectionStateChangedEvent`, async listener

---

## 11. Design Sections (to be continued)

- [x] Protocol SDK contract details
- [x] Connection Manager design
- [x] Config Repository design
- [x] Gateway Engine design
- [x] Framework Adapter design
- [x] Logging & Events design
- [x] Error handling & retry strategy
- [x] Testing strategy
- [x] Package naming & repository structure
- [x] Implementation phasing / roadmap

---

## 12. Implementation Phasing

### Phase 1: Kernel + Modbus (MVP)

| Task | Content |
|------|---------|
| Kernel skeleton | `Kernel::boot()`, ProtocolRegistry, ConfigRepository (File), LogDriver (PSR), Event |
| SDK interfaces | ProtocolInterface, ConnectorInterface, DriverInterface, FrameInterface, DataPointInterface |
| Coroutine layer | CoroutineAdapterInterface + FiberAdapter + SyncAdapter (Swoole/Swow later) |
| ConnectionManager | Lazy + Eager strategy, health check, reconnection, exception hierarchy |
| Framework adapters | PlainPhpAdapter + framework detect chain |
| Modbus TCP | Pure PHP socket, Frame encode/decode, Holding/Input/Coil registers |
| Modbus RTU | Serial communication (ext-dio or FFI) |
| Tests | Modbus Mock Server + Connector simulation ≥80% |
| Docs | README, Quick Start, Modbus config examples |

### Phase 2: Protocol Extension + Gateway

| Task | Content |
|------|---------|
| OPC UA | Client mode, binary protocol stack, Security Policy |
| Gateway Engine | Full implementation: Rule Loader/Scheduler/Runner, poll/change/cron, Circuit Breaker |
| Coroutine enhancement | SwooleAdapter + SwowAdapter, PooledStrategy |
| Config | DatabaseConfigRepository (MySQL/SQLite) |
| Laravel Adapter | ServiceProvider + Facade + artisan commands + config publish |
| Webman Adapter | config/plugin auto-discovery |
| Tests | OPC UA Mock Server, Gateway rule simulation |

### Phase 3: More Protocols + More Frameworks

| Task | Content |
|------|---------|
| BACnet IP | Who-Is/I-Am, ReadProperty, WriteProperty |
| EtherNet/IP | CIP stack, Class 1/3 connections |
| Profinet | Real-time + non-real-time channels (may need FFI/C bridge) |
| Hyperf Adapter | ConfigProvider + DI + coroutine/pool deep integration |
| ThinkPHP Adapter | services.php auto-discovery |
| Yii2 Adapter | Bootstrap + DI Container |

### Phase 4: Production Ready

| Task | Content |
|------|---------|
| E2E tests | Docker-based protocol simulators |
| Performance | Concurrent read/write, bulk point scan, gateway throughput |
| Metrics | MetricsCollector + Prometheus export |
| Alert channels | DingTalk/Feishu/Email/Webhook |
| Docs | Protocol API docs, framework integration guides, gateway config guides |
| Security audit | OPC UA certificate management, Modbus security, input validation |

### Phase Dependencies

```
Phase 1 (Kernel + Modbus)
    │
    ▼
Phase 2 (OPC UA + Gateway + Laravel + Webman)
    │
    ▼
Phase 3 (BACnet + EtherNet/IP + Profinet + Hyperf + ThinkPHP + Yii2)
    │
    ▼
Phase 4 (E2E + Perf + Monitoring + Docs)
```

---

## 13. Appendix: Full Requirements Traceability

| # | Question | Decision |
|---|----------|----------|
| 1 | Protocol scope | All major protocols: Modbus, Profinet, EtherNet/IP, OPC UA, BACnet |
| 2 | Core use cases | Data acquisition + device control + protocol gateway/conversion |
| 3 | Async support | Sync-first; framework adapters handle coroutine/fiber adaptation |
| 4 | PHP version | ≥ 8.1 (Fiber, enums, readonly properties) |
| 5 | Protocol implementation | Simple protocols pure PHP socket; complex protocols FFI or bridge |
| 6 | Framework integration | Single package auto-discovery, detect runtime, plug and play |
| 7 | Config management | File-based default + Repository interface for database-backed config |
| 8 | Testing strategy | TDD first (protocol simulation tests ≥80% coverage), scale to full E2E |
| 9 | Architecture pattern | Micro-kernel + Protocol SDK (Approach C) |
| 10 | Coroutine support | All frameworks support Swoole; Kernel unifies coroutine layer |
| 11 | Gateway trigger modes | poll, change, cron |
| 12 | Error delivery | Exception (sync) + Event (async) dual channel |
| 13 | Connection strategies | EAGER, LAZY, POOLED |
| 14 | Retry backoff | Exponential with jitter (default), Fixed, NoRetry |
