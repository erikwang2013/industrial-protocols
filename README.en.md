# Industrial Protocols PHP

PHP industrial network communication protocol plugin — micro-kernel + protocol SDK architecture supporting Modbus, BACnet, EtherNet/IP and more. Compatible with Plain PHP, Laravel, Webman, Hyperf, ThinkPHP, and Yii2.

> [中文版](README.md)

---

## Table of Contents

- [Design Philosophy](#design-philosophy)
- [Architecture](#architecture)
  - [System Architecture](#system-architecture)
  - [Package Dependencies (one-way)](#package-dependencies-one-way)
  - [Connection Lifecycle](#connection-lifecycle)
  - [Gateway Engine Data Flow](#gateway-engine-data-flow)
  - [Data Transform Pipeline](#data-transform-pipeline)
  - [Exception Hierarchy](#exception-hierarchy)
  - [Directory Structure](#directory-structure)
  - [Bridge Layer Architecture](#bridge-layer-architecture)
  - [Protocol Implementation Comparison](#protocol-implementation-comparison)
- [Feature List](#feature-list)
- [Reference Manual](#reference-manual)
  - [Requirements Summary](#requirements-summary)
  - [Connection Strategy Details](#connection-strategy-details)
  - [Built-in Implementations](#built-in-implementations)
  - [Framework Integration Mechanisms](#framework-integration-mechanisms)
  - [Coroutine Support Matrix](#coroutine-support-matrix)
  - [Log Level Conventions](#log-level-conventions)
  - [Retry Strategy Comparison](#retry-strategy-comparison)
  - [Exception Reference](#exception-reference)
  - [Full Capability Matrix](#full-capability-matrix)
- [Vendor Adapters](#vendor-adapters)
- [Supported Industrial Protocols](#supported-industrial-protocols)
  - [Industrial Ethernet](#industrial-ethernet)
  - [Fieldbus Protocols](#fieldbus-protocols)
  - [IoT & Specialized Protocols](#iot--specialized-protocols)
  - [Hardware-Dependent (Bridge)](#hardware-dependent-bridge)
- [Supported Frameworks](#supported-frameworks)
- [Quick Start](#quick-start)
- [Usage Guide](#usage-guide)
- [Protocol Examples](#protocol-examples)
  - [Modbus TCP](#modbus-tcp)
  - [BACnet/IP](#bacnetip-1)
  - [EtherNet/IP](#ethernetip-2)
  - [OPC UA Binary](#opc-ua-binary-1)
  - [Profinet NRT](#profinet-nrt-2)
  - [Modbus RTU (Serial)](#modbus-rtu-serial)
  - [HART](#hart)
  - [CC-Link RS-485](#cc-link-rs-485-1)
  - [MQTT](#mqtt)
  - [DNP3](#dnp3)
  - [IEC 61850 (MMS)](#iec-61850-mms)
  - [PROFIBUS / CANopen / DeviceNet](#profibus--canopen--devicenet-fieldbus-bridge)
  - [Hardware Bridge](#hardware-bridge-ethercat--powerlink--sercos-iii)
  - [LIN (Automotive)](#lin-automotive-body-bus)
  - [K-Line (OBD-II)](#k-line-obd-ii-diagnostics)
  - [HART-IP](#hart-ip)
  - [DALI (Lighting)](#dali-digital-lighting)
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
│  ├──────────┼──────────┼──────────┼──────────┼───────────┤  │
│  │  Vendor  │  Bridge  │          │          │           │  │
│  │ Profiles │  Layer   │          │          │           │  │
│  └──────────┴──────────┴──────────┴──────────┴───────────┘  │
├─────────────────────────────────────────────────────────────┤
│                   Protocol SDK (Contracts)                   │
│  ProtocolInterface │ ConnectorInterface │ DriverInterface    │
│  FrameInterface    │ DataPointInterface │ GatewayRuleInterface│
├─────────────────────────────────────────────────────────────┤
│              Protocol Packages (SDK Implementations)          │
│  Modbus(TCP/RTU)│ BACnet/IP│ EtherNet/IP  │ OPC UA (TCP) │
│  Profinet(NRT)  │ HART     │ CC-Link      │ MQTT · DNP3  │
│  IEC 61850(MMS) │ LIN      │ K-Line       │ HART-IP      │
│  EtherCAT*      │ POWERLINK*│ SERCOS III* │ PROFIBUS*    │
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

### Directory Structure

```
industrial-protocols/
├── packages/
│   ├── kernel/                         # Micro-kernel
│   │   ├── src/
│   │   │   ├── Kernel.php              # Boot entry point
│   │   │   ├── Protocol/               # SDK interfaces + ProtocolRegistry
│   │   │   ├── Connection/             # ConnectionManager + strategies
│   │   │   ├── Config/                 # Config Repository
│   │   │   ├── Gateway/                # GatewayEngine + CircuitBreaker
│   │   │   ├── Coroutine/              # Coroutine adapters + factory
│   │   │   ├── Framework/              # Framework adapters (6 frameworks)
│   │   │   ├── Bridge/                 # Hardware bridge layer
│   │   │   ├── Vendor/                 # Vendor profiles (12 vendors)
│   │   │   ├── Event/                  # 13 event types (PSR-14)
│   │   │   ├── Log/                    # Log drivers (PSR-3/File/Null)
│   │   │   ├── Retry/                  # Retry strategies (4 types)
│   │   │   ├── Metrics/                # Metrics collector (Prometheus)
│   │   │   ├── Alert/                  # Alert channels (Webhook/Log)
│   │   │   ├── Security/               # Input validation
│   │   │   └── Exception/              # 20+ exception hierarchy
│   │   ├── config/                     # Default templates
│   │   └── tests/
│   │
│   ├── Industrial Ethernet (5):  modbus/  bacnet/  ethernetip/  opcua/  profinet/
│   ├── Fieldbus (12):            hart/  cclink/  profibus/  canopen/  devicenet/
│   │                              foundationfieldbus/  asinterface/  iolink/
│   │                              cclinkie/  dnp3/  iec61850/
│   ├── Automotive (3):           lin/  kline/  flexray/
│   ├── Building (2):             lonworks/  dali/
│   ├── IoT (5):                  mqtt/  hartip/  isa100/  wirelesshart/  saej1850/
│   ├── System Bus (3):           pci/  vme/  cpci/
│   ├── Hardware Bridge (8):      ethercat/  powerlink/  sercos/  most/
│   │                              controlnet/  interbus/  worldfip/  lightbus/
│   └── Legacy (2):               modbusplus/  sercos1/
│
├── docker/                         # Docker simulators
├── docs/                           # Design docs + guides (bilingual)
├── tests/                          # Integration + E2E tests
├── composer.json                   # Root monorepo config
└── phpunit.xml
```

### Bridge Layer Architecture

The Bridge layer connects PHP applications to protocols requiring dedicated hardware, adapting vendor C/C++ SDKs and gateway devices through a unified interface.

```
┌──────────────────────────────────────────────────────────────┐
│                    Protocol Packages                          │
│  EtherCAT · POWERLINK · SERCOS III · Profinet RT · TSN       │
│  (implement ConnectorInterface via BridgeConnector)            │
├──────────────────────────────────────────────────────────────┤
│                     BridgeConnector                           │
│  implements ConnectorInterface, delegates to BridgeInterface   │
├──────────────────────────────────────────────────────────────┤
│                     BridgeInterface                           │
│  open() · close() · execute(command, data) · isReady()       │
├─────────────────────┬────────────────────────────────────────┤
│ ExternalProcessBridge│         TcpGatewayBridge                │
│ ┌─────────────────┐  │  ┌──────────────────────────────────┐  │
│ │ C/C++ SDK proc   │  │  │ Gateway Hardware (Anybus/netX)   │  │
│ │ proc_open()      │  │  │ stream_socket_client()           │  │
│ │ stdin/stdout     │  │  │ TCP/UDP                          │  │
│ └────────┬────────┘  │  └───────────────┬──────────────────┘  │
│          │            │                  │                      │
│   ┌──────▼──────┐     │     ┌───────────▼──────────┐          │
│   │ TwinCAT ADS  │     │     │ Anybus · netX · MGate│          │
│   │ openPOWERLINK│     │     │ ctrlX CORE · AXL F   │          │
│   │ SOEM         │     │     │ S7-1500 · IndraDrive │          │
│   └─────────────┘     │     └──────────────────────┘          │
│   (Local C/C++ SDK)   │     (Remote/Embedded Gateway HW)      │
└─────────────────────┴────────────────────────────────────────┘
                              │
                    ┌─────────▼─────────┐
                    │  VendorBridgeFactory │
                    │  8 pre-configured    │
                    │  vendor profiles     │
                    │  create(vendor,      │
                    │    device, version)  │
                    └─────────────────────┘
```

| Bridge | Class | Transport | Use Case |
|--------|-------|-----------|----------|
| External Process | `ExternalProcessBridge` | `proc_open` stdin/stdout | Local C/C++ SDK (Beckhoff TwinCAT, openPOWERLINK) |
| Gateway Hardware | `TcpGatewayBridge` | TCP/UDP Socket | Remote gateway (Hilscher netX, HMS Anybus, Moxa MGate) |

### Protocol Implementation Comparison

```
┌─────────────────────────────────────────────────────────┐
│  Pure PHP (Application-Layer Protocols)                  │
│  ┌──────────┬──────────┬──────────┬──────────────────┐  │
│  │ Modbus   │ BACnet   │ EIP      │ OPC UA           │  │
│  │ (TCP/RTU)│ (UDP)    │ (TCP)    │ (UA Binary/TCP)  │  │
│  │ Profinet │ HART     │ CC-Link  │                  │  │
│  │ (NRT)    │ (FSK)    │ (RS-485) │                  │  │
│  └──────────┴──────────┴──────────┴──────────────────┘  │
│  Standard sockets, full protocol stack in pure PHP      │
├─────────────────────────────────────────────────────────┤
│  Bridge (Hardware-Dependent Protocols)                   │
│  ┌──────────┬──────────┬──────────┬──────────────────┐  │
│  │ EtherCAT │ POWERLINK│ SERCOS   │ Profinet RT      │  │
│  │ (ESC chip)│(openMAC)│(FPGA IP) │ (ERTEC)          │  │
│  │ TSN      │          │          │                  │  │
│  │ (TSN NIC)│          │          │                  │  │
│  └──────────┴──────────┴──────────┴──────────────────┘  │
│  Hardware-layer, adapted via BridgeInterface to SDK/HW  │
└─────────────────────────────────────────────────────────┘
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
| Hardware Bridge | BridgeInterface + ExternalProcessBridge + TcpGatewayBridge, adapts C/C++ SDKs and gateway hardware |
| Vendor Adapters | 12 pre-configured vendors (Beckhoff/Siemens/B&R/Bosch Rexroth/Hilscher/HMS/Moxa/Phoenix Contact/Bihl+Wiedemann/ifm electronic/Pepperl+Fuchs/Softing), VendorBridgeFactory one-click bridge creation |

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

## Reference Manual

### Requirements Summary

All 14 key design decisions:

| # | Decision | Choice |
|---|----------|--------|
| 1 | Protocol Scope | Full coverage: Modbus, Profinet, EtherNet/IP, OPC UA, BACnet |
| 2 | Core Scenarios | Data acquisition + device control + protocol gateway/conversion |
| 3 | Async Support | Primarily synchronous with unified kernel coroutine layer |
| 4 | PHP Version | >= 8.1 (Fiber, enums, readonly) |
| 5 | Protocol Implementation | Simple protocols: pure PHP sockets; complex protocols: FFI or bridging |
| 6 | Framework Integration | Single-package auto-discovery; plug-and-play environment detection |
| 7 | Config Management | File-based default + Repository interface for DB swap |
| 8 | Testing Strategy | TDD-first; protocol simulation tests >=80%; gradual E2E coverage |
| 9 | Architecture Pattern | Micro-kernel + Protocol SDK (Option C) |
| 10 | Coroutine Support | Swoole-capable across all frameworks; unified kernel coroutine layer |
| 11 | Gateway Triggers | poll (periodic), change (value-triggered), cron (schedule-based) |
| 12 | Error Propagation | Dual channel: exceptions (sync) + events (async) |
| 13 | Connection Strategies | EAGER (boot-time connect), LAZY (on-demand), POOLED (connection pool) |
| 14 | Retry Backoff | Exponential backoff + random jitter (default), fixed interval, no retry |

### Connection Strategy Details

| Strategy | Class | Behavior | Use Case | FPM | Persistent Process |
|----------|-------|----------|----------|-----|-------------------|
| LAZY (default) | `LazyStrategy` | Connects on first read/write; caches for reuse | Many devices, intermittent access | Recommended | — |
| EAGER | `EagerStrategy` | Connects to all configured devices at boot() | Few devices, latency-sensitive | — | Recommended |
| POOLED | `PooledStrategy` | Pre-creates N connections (default 4); round-robin allocation | High-frequency polling, gateways | — | Recommended |

### Built-in Implementations

| Component | Interface | Built-in Implementations |
|-----------|-----------|-------------------------|
| Config Repository | `ConfigRepositoryInterface` | `FileConfigRepository` / `DatabaseConfigRepository` / `EnvConfigRepository` |
| Coroutine Adapter | `CoroutineAdapterInterface` | `SwooleCoroutineAdapter` / `FiberCoroutineAdapter` / `SyncCoroutineAdapter` |
| Log Driver | `LogDriverInterface` | `PsrLogDriver` / `FileLogDriver` / `NullLogDriver` |
| Retry Strategy | `RetryStrategyInterface` | `NoRetryStrategy` / `FixedRetryStrategy` / `ExponentialBackoffStrategy` |
| Connection Strategy | `StrategyInterface` | `LazyStrategy` / `EagerStrategy` / `PooledStrategy` |
| Alert Channel | `AlertChannelInterface` | `WebhookAlertChannel` / `LogAlertChannel` |
| Event Dispatch | PSR-14 `EventDispatcherInterface` | Built-in anonymous implementation / framework adapter injection |

### Framework Integration Mechanisms

| Framework | Detection | Config Mechanism | Service Registration | CLI Commands | Coroutine Support |
|-----------|-----------|-----------------|---------------------|-------------|-------------------|
| Plain PHP | Default fallback | Direct config_path | Manual new Kernel | None | Fiber |
| Laravel | `Illuminate\Foundation\Application` | ServiceProvider::publishes() | Singleton + Facade | `industrial:connect` / `industrial:gateway:list` | Octane (Swoole) |
| Webman | `Workerman\Worker` | config/plugin/ auto-discovery | ProtocolProcess::onWorkerStart | None | Swoole Event Driver / Fiber |
| Hyperf | `Hyperf\Framework\ApplicationFactory` | ConfigProvider + config/autoload/ | KernelFactory DI binding | `industrial:connect` / `gateway:list` | Swoole native |
| ThinkPHP | `think\App` | services.php auto-discovery | IndustrialProtocolsService::boot() | None | think-swoole |
| Yii2 | `yii\base\Application` | Bootstrap + config/web.php | Application component registration | None | swoole-yii2 |

### Coroutine Support Matrix

| Runtime | Adapter Class | Detection | Supported Frameworks | parallel() Implementation |
|---------|--------------|-----------|---------------------|--------------------------|
| Swoole | `SwooleCoroutineAdapter` | `extension_loaded('swoole') && Co::getCid()>0` | Laravel / Webman / Hyperf / ThinkPHP / Yii2 | WaitGroup concurrency |
| Fiber | `FiberCoroutineAdapter` | `PHP_VERSION_ID >= 80100` | All frameworks | Fiber::start() sequential |
| Sync (fallback) | `SyncCoroutineAdapter` | Always available | All frameworks | foreach sequential |

Detection priority: `Swoole -> Fiber -> Sync`

### Log Level Conventions

| Level | Scenario | Example |
|-------|----------|---------|
| DEBUG | Read/write operations | `Read 40001-40010 from plc-001 (23ms)` |
| INFO | Connection established/closed | `Device plc-001 connected (Modbus TCP 192.168.1.10:502)` |
| WARNING | Reconnection attempts | `Reconnecting plc-001, attempt 2/3` |
| ERROR | Read/write failures | `Write 40001 failed: timeout after 3000ms` |
| CRITICAL | Circuit breaker triggered | `Gateway rule gw-001 circuit breaker OPENED after 5 failures` |

### Retry Strategy Comparison

| Strategy | maxAttempts | delay (1st/2nd/3rd) | jitter | Use Case |
|----------|------------|---------------------|--------|----------|
| `NoRetryStrategy` | 0 | 0 / 0 / 0 | — | Write operations (idempotency risk), non-retryable exceptions |
| `FixedRetryStrategy` | 3 | 1000 / 1000 / 1000ms | — | Simple retries at fixed intervals |
| `ExponentialBackoffStrategy` (default) | 3 | 1000 / 2000 / 4000ms | Optional | Read operations, connection establishment |
| ExponentialBackoff + Jitter | 3 | 500~1500 / 1000~3000 / 2000~6000ms | Enforced random | Multi-device scenarios (thundering herd prevention) |

### Exception Reference

| Exception Class | Layer | Trigger Condition | Retryable? | Event Triggered |
|-----------------|-------|-------------------|-----------|-----------------|
| `ConnectionTimeoutException` | Connection | TCP connection timeout | Yes (up to 3x) | `ConnectionRetryEvent` |
| `ConnectionRefusedException` | Connection | Device refused connection | Yes (up to 3x) | `ConnectionRetryEvent` |
| `ConnectionClosedException` | Connection | Connection unexpectedly closed | Yes | `ConnectionStateChangedEvent` |
| `FrameException` | Protocol | Illegal frame format | No | `DataErrorEvent` |
| `CrcException` | Protocol | CRC/checksum mismatch | Yes (up to 1x) | `DataErrorEvent` |
| `DeviceBusyException` | Device | Device returned busy signal | Yes (with delay) | `DataErrorEvent` |
| `AddressOutOfRangeException` | Device | Address exceeds valid range | No | `DataErrorEvent` |
| `CircuitBreakerOpenException` | Gateway | Circuit breaker open | No | `GatewayCircuitBreakerEvent` |
| `RuleValidationException` | Gateway | Gateway rule validation failed | No | `GatewayRuleFailedEvent` |

### Full Capability Matrix

| Capability | Plain PHP | Laravel | Webman | Hyperf | ThinkPHP | Yii2 |
|------------|----------|---------|--------|--------|----------|------|
| Framework Detection | Fallback | Application class | Worker class | ApplicationFactory | think\App | yii\base\Application |
| Config Discovery | Manual | artisan vendor:publish | config/plugin auto | ConfigProvider | services.php | Bootstrap |
| CLI Commands | — | ✅ industrial:connect / gateway:list | — | ✅ connect / gateway:list | — | — |
| Facade/Quick Access | Kernel instance | IndustrialProtocolsFacade | N/A | DI Container | Static singleton | Yii component |
| Swoole Coroutine | ✅ SwooleAdapter | ✅ Octane | ✅ Event Driver | ✅ Native | ✅ think-swoole | ✅ swoole-yii2 |
| Fiber Coroutine | ✅ | ✅ Octane | ✅ workerman 5.x | — | — | — |
| Persistent Process | — | ✅ Octane | ✅ | ✅ | — | ✅ swoole-yii2 |
| Connection Pool | ✅ PooledStrategy | ✅ | ✅ | ✅ | — | ✅ |
| Gateway Engine | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Circuit Breaker | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Monitoring Metrics | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Alert Channels | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Input Validation | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Database Config | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Hardware Bridge | ✅ Bridge | ✅ Bridge | ✅ Bridge | ✅ Bridge | ✅ Bridge | ✅ Bridge |
| Vendor Adapters | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Vendor Adapters

The kernel includes pre-configured profiles for 12 major industrial hardware vendors, eliminating the need to manually look up SDK paths and port numbers.

### Vendor List

| Vendor | Protocol | Bridge Type | Device Count |
|--------|----------|-----------|-------------|
| Beckhoff | EtherCAT | ExternalProcessBridge | 6 |
| Siemens | PROFINET | TcpGatewayBridge | 5 |
| B&R | POWERLINK | ExternalProcessBridge | 4 |
| Bosch Rexroth | SERCOS III | TcpGatewayBridge | 4 |
| Hilscher | Multi-protocol | TcpGatewayBridge | 4 |
| HMS/Anybus | Multi-protocol | TcpGatewayBridge | 4 |
| Moxa | Multi-protocol | TcpGatewayBridge | 4 |
| Phoenix Contact | PROFINET/EIP | TcpGatewayBridge | 4 |
| Bihl+Wiedemann | AS-Interface | TcpGatewayBridge | 2 |
| ifm electronic | IO-Link | TcpGatewayBridge | 2 |
| Pepperl+Fuchs | AS-i / HART | TcpGatewayBridge | 2 |
| Softing | FF / PROFIBUS | ExternalProcessBridge | 2 |

### Usage

```php
// Get vendor factory
$factory = $kernel->getVendorBridgeFactory();

// List all supported vendors (12 total)
$vendors = $factory->listVendors();

// View a vendor's device models
$devices = $factory->getDevices('siemens');
// → [S7-1200 V4.x, S7-1500 V3.x, ET 200SP V2.x, ET 200MP V2.x, S7-400 V6.x]

// One-click bridge creation — specify vendor, model, version
$bridge = $factory->create('beckhoff', 'CX2030', '3.1');
// Returns a pre-configured ExternalProcessBridge with SDK path auto-filled

// Override default parameters
$bridge = $factory->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
    'port' => 34964,
]);

// Connect and read
$conn = new BridgeConnector($bridge, 'ethercat');
$conn->connect();
$result = $conn->read('0x6000:0x01');
```

### Configuration Merge Priority

```
Vendor defaults → Device model overrides → User custom parameters
```

See [Vendor Adapters Reference](docs/en/vendors.md).

---

## Supported Industrial Protocols

### Industrial Ethernet Protocols

| Protocol | Phase | Variant | Implementation | Operations |
|----------|-------|---------|---------------|------------|
| **Modbus TCP** | Phase 1 | TCP | Pure PHP Socket | FC 01/03/04/06/10 |
| **BACnet/IP** | Phase 3 | IP (UDP) | Pure PHP UDP Socket | Who-Is/I-Am, ReadProperty |
| **EtherNet/IP** | Phase 3 | TCP | Pure PHP Socket | ENIP session, CIP Read Tag |
| **OPC UA** | Phase 4 | Binary | Pure PHP UA Binary Stack | CreateSession, Read, Write, Browse |
| **Profinet NRT** | Phase 4 | NRT | Pure PHP UDP/TCP | DCP discovery, Record Data read/write |

### Fieldbus Protocols

| Protocol | Variant | Implementation | Description |
|----------|---------|---------------|-------------|
| **Modbus RTU/ASCII** | RS-485 Serial | Pure PHP Serial | CRC16 check, stty config |
| **HART** | 4-20mA FSK | Pure PHP Serial | HART modem, PV/loop current |
| **CC-Link** | RS-485 | Pure PHP Serial | Master-slave polling, CRC-16/XMODEM |
| **DNP3** | TCP/Serial | Pure PHP | Power automation, Class 0 poll, CRC-16/DNP |
| **IEC 61850** | MMS | Pure PHP TCP | Substation automation, IED data paths, TPKT |

### IoT & Specialized Protocols

| Protocol | Use Case | Method | Port |
|----------|----------|--------|------|
| **MQTT** | Lightweight IoT | Pure PHP TCP | 1883 |
| **ISA100.11a** | Industrial wireless mesh | Bridge (802.15.4 gateway) | — |
| **WirelessHART** | HART wireless mesh | Bridge (WirelessHART gateway) | — |
| **HART-IP** | HART over TCP/UDP | Pure PHP TCP | 5094 |

### Automotive & Vehicle Bus Protocols

| Protocol | Use Case | Method | Notes |
|----------|----------|--------|-------|
| **LIN** | Low-cost body bus | Pure PHP Serial | 19200 bps, master-slave, PID parity |
| **K-Line** | OBD-II diagnostics | Pure PHP Serial | ISO 9141/14230, 5-baud init |
| **FlexRay** | Deterministic high-speed | Bridge | 10 Mbps, needs FlexRay controller |
| **MOST** | Fiber multimedia | Bridge | Needs MOST interface |
| **SAE J1850** | OBD-II early standard | Bridge | PWM/VPW, needs J1850 interface |

### Building Automation & Lighting

| Protocol | Use Case | Method | Notes |
|----------|----------|--------|-------|
| **DALI** | Digital lighting | Bridge | Needs DALI gateway (Lunatone/Helvar) |

### System & Backplane Buses

| Protocol | Use Case | Method | Notes |
|----------|----------|--------|-------|
| **PCI / PCIe** | System bus | Bridge | Needs kernel driver/library bridge |
| **VME / VPX** | Industrial backplane | Bridge | Needs VME bridge |
| **CPCI** | CompactPCI | Bridge | Needs CPCI interface |

### Other

| Protocol | Use Case | Method | Notes |
|----------|----------|--------|-------|
| **SERCOS I/II** | Early fiber SERCOS | Bridge | Distinct from SERCOS III, needs fiber interface |
| **PROFIBUS** | DP / PA / FMS | Bridge | Siemens CP 5611 / Anybus / Hilscher |
| **CANopen** | CAN | Bridge | PCAN-USB / IXXAT / SocketCAN |
| **DeviceNet** | CAN | Bridge | Anybus DeviceNet Scanner |
| **Foundation Fieldbus** | H1 / HSE | Bridge | NI USB-8486 / Softing FFusb |
| **AS-Interface** | AS-i | Bridge | Bihl+Wiedemann / Pepperl+Fuchs |
| **IO-Link** | Point-to-Point | Bridge | ifm / Balluff IO-Link Master |
| **CC-Link IE** | Industrial Ethernet | Bridge | CC-Link IE Field gateway |

### Hardware-Dependent Protocols (Bridge)

| Protocol | Hardware Required |
|----------|------------------|
| **EtherCAT** | ESC chip (Beckhoff TwinCAT / SOEM) |
| **POWERLINK** | openMAC (openPOWERLINK / B&R) |
| **SERCOS III** | FPGA IP core (Bosch Rexroth / Hilscher) |
| **Profinet RT/IRT** | ERTEC chip (Siemens / Hilscher) |
| **TSN** | TSN NIC (Intel I225 / NXP SJA1110) |
| **ControlNet** | Coax token-ring interface (Allen-Bradley) |
| **INTERBUS** | Ring network interface (Phoenix Contact) |
| **LonWorks** | Neuron chip / interface card |
| **WorldFIP** | FIP bus interface |
| **Lightbus** | Fiber optic interface (Beckhoff) |
| **Modbus Plus** | Token-ring interface (Schneider) |

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

### OPC UA Binary

```php
use Erikwang2013\IndustrialProtocols\OpcUa\OpcUaProtocol;

$kernel->getProtocolRegistry()->register(new OpcUaProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('opcua-server');

// Read CurrentTime node
$result = $conn->read('i=2258');

// Browse address space
$children = $conn->browse('i=85');

// Write node
$conn->write(['ns=2;s=SetPoint' => 100.0]);
```

### Profinet NRT

```php
use Erikwang2013\IndustrialProtocols\Profinet\ProfinetProtocol;

$kernel->getProtocolRegistry()->register(new ProfinetProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('pn-device');

// DCP device discovery
$devices = $conn->discoverDevices(5);

// Read Record Data (api:slot:subslot:index)
$result = $conn->read('0:0:1:0xAFF0');
```

### Modbus RTU (Serial)

```php
$conn = $kernel->getConnectionManager()->connect('plc-rtu', [
    'protocol' => 'modbus', 'variant' => 'rtu',
    'device'   => '/dev/ttyUSB0', 'baud_rate' => 19200,
    'unit_id'  => 1,
]);
$result = $conn->read('40001');
```

### HART

```php
use Erikwang2013\IndustrialProtocols\Hart\HartProtocol;

$kernel->getProtocolRegistry()->register(new HartProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('hart-device', [
    'protocol' => 'hart', 'device' => '/dev/ttyUSB1',
]);
$pv = $conn->read('pv');           // Primary Variable
$current = $conn->read('loop_current'); // Loop current (mA)
```

### CC-Link RS-485

```php
use Erikwang2013\IndustrialProtocols\CcLink\CcLinkProtocol;

$kernel->getProtocolRegistry()->register(new CcLinkProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('cclink-device', [
    'protocol'  => 'cc-link', 'variant' => 'rs485',
    'device'    => '/dev/ttyUSB2', 'baud_rate' => 156000,
]);
$result = $conn->read('RWw0'); // Read remote register
```

### MQTT

```php
use Erikwang2013\IndustrialProtocols\Mqtt\MqttProtocol;

$kernel->getProtocolRegistry()->register(new MqttProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('mqtt-broker', [
    'protocol' => 'mqtt', 'host' => '192.168.1.100',
    'port' => 1883, 'client_id' => 'php-client', 'keep_alive' => 60,
]);
$conn->write(['sensors/temperature' => '23.5']);  // publish
$result = $conn->read('sensors/#');                // subscribe wildcard
```

### DNP3

```php
use Erikwang2013\IndustrialProtocols\Dnp3\Dnp3Protocol;

$kernel->getProtocolRegistry()->register(new Dnp3Protocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('rtu-001', [
    'protocol' => 'dnp3', 'host' => '10.0.1.50', 'port' => 20000,
]);
$result = $conn->read('30:1:5'); // Class 0: Group 30, Var 1, Index 5
```

### IEC 61850 (MMS)

```php
use Erikwang2013\IndustrialProtocols\Iec61850\Iec61850Protocol;

$kernel->getProtocolRegistry()->register(new Iec61850Protocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('ied-001', [
    'protocol' => 'iec61850', 'variant' => 'mms',
    'host' => '10.0.1.100', 'port' => 102,
]);
$result = $conn->read('IED1/MMXU1.MX.A.phsA');
```

### PROFIBUS / CANopen / DeviceNet (Fieldbus Bridge)

```php
use Erikwang2013\IndustrialProtocols\Profibus\ProfibusProtocol;
use IndustrialProtocols\Bridge\TcpGatewayBridge;

// Connect to PROFIBUS via Anybus gateway
$bridge = new TcpGatewayBridge('192.168.1.200', 502);
$kernel->getProtocolRegistry()->register(new ProfibusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('profibus-device', [
    'protocol' => 'profibus', 'variant' => 'dp', 'bridge' => $bridge,
]);
$result = $conn->read('0x0000:0x0001');

// Or one-click via vendor factory
$bridge = $kernel->getVendorBridgeFactory()->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
]);
```

### Hardware Bridge Protocols (EtherCAT / POWERLINK / SERCOS III)

```php
use IndustrialProtocols\Bridge\ExternalProcessBridge;
use IndustrialProtocols\EtherCat\EtherCatProtocol;

// Bridge EtherCAT via C/C++ SDK
$bridge = new ExternalProcessBridge('/opt/ethercat-sdk/ecat_master');

$kernel->getProtocolRegistry()->register(new EtherCatProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('ethercat-device', [
    'protocol' => 'ethercat',
    'bridge'   => $bridge,
]);
$result = $conn->read('0x6000:0x01'); // CoE SDO read
```

### LIN (Automotive Body Bus)

```php
use Erikwang2013\IndustrialProtocols\Lin\LinProtocol;

$conn = $kernel->getConnectionManager()->connect('lin-device', [
    'protocol' => 'lin', 'variant' => 'master',
    'device'   => '/dev/ttyUSB3', 'baud_rate' => 19200,
]);
$result = $conn->read('0x3C');
```

### K-Line (OBD-II Diagnostics)

```php
use Erikwang2013\IndustrialProtocols\KLine\KLineProtocol;

$conn = $kernel->getConnectionManager()->connect('obd-ii', [
    'protocol' => 'k-line', 'device' => '/dev/ttyUSB4',
]);
$result = $conn->read('010C'); // OBD-II PID 0x0C (engine RPM)
```

### HART-IP

```php
use Erikwang2013\IndustrialProtocols\HartIp\HartIpProtocol;

$conn = $kernel->getConnectionManager()->connect('hart-ip', [
    'protocol' => 'hart-ip', 'host' => '192.168.1.150', 'port' => 5094,
]);
$pv = $conn->read('pv');
```

### DALI (Digital Lighting)

```php
$bridge = new TcpGatewayBridge('192.168.1.200', 502);
$conn = $kernel->getConnectionManager()->connect('dali-gw', [
    'protocol' => 'dali', 'bridge' => $bridge,
]);
$conn->write(['0x00' => 254]); // broadcast dim to 100%
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

- [Protocol API Reference](docs/en/protocols.md) — Connection config, read/write ops, address formats for 42 protocols
- [Framework Integration Guide](docs/en/framework-integration.md) — Detailed integration for 6 frameworks
- [Gateway Engine Guide](docs/en/gateway.md) — Rules, trigger modes, circuit breaker
- [Security Guide](docs/en/security.md) — Input validation, best practices, exception reference
- [Vendor Adapters Reference](docs/en/vendors.md) — Pre-configured profiles, device models, and SDK paths for 12 vendors

---

## Requirements

- PHP >= 8.1
- Composer
- Optional: ext-swoole (Swoole coroutine acceleration)
- Optional: ext-pdo (database config storage)
- Optional: serial port permissions (Modbus RTU / HART / LIN / K-Line / CC-Link)
- Optional: C/C++ SDK (EtherCAT / POWERLINK / FlexRay bridge)
- Optional: gateway hardware (PROFIBUS / SERCOS / DALI / IO-Link / fieldbus bridging)

---

## License

MIT
