# 工业协议 PHP 插件 — 设计规范
> [English](2026-07-21-industrial-protocols-design-en.md)

**日期:** 2026-07-21  
**状态:** 进行中  
**作者:** Erik

---

## 1. 需求摘要

| # | 问题 | 决策 |
|---|----------|----------|
| 1 | 协议范围 | 所有主流协议：Modbus、Profinet、EtherNet/IP、OPC UA、BACnet 等 |
| 2 | 核心用例 | 数据采集 + 设备控制 + 协议网关/转换 |
| 3 | 异步支持 | 同步优先；由框架适配器处理协程/Fiber 适配 |
| 4 | PHP 版本 | ≥ 8.1（Fiber、枚举、只读属性） |
| 5 | 协议实现 | 分层策略：简单协议（Modbus、BACnet）纯 PHP socket；复杂协议（OPC UA、EtherNet/IP、Profinet）FFI 或桥接 |
| 6 | 框架集成 | 单包自动发现：检测运行时环境，即插即用 |
| 7 | 配置管理 | 默认基于文件 + Repository 接口支持数据库存储配置；简单场景用文件，复杂场景用数据库 |
| 8 | 测试策略 | TDD 优先（协议模拟测试覆盖率 ≥80%），逐步扩展到完整 E2E |
| 9 | 架构模式 | 微内核 + 协议 SDK（方案 C） |

---

## 2. 整体架构

```
┌─────────────────────────────────────────────────────────────┐
│                      用户应用程序                            │
├─────────────────────────────────────────────────────────────┤
│              框架适配器（自动发现）                           │
│         Laravel  │  Webman  │  Hyperf  │  ThinkPHP  │  Yii  │
├─────────────────────────────────────────────────────────────┤
│                     微内核（核心）                            │
│  ┌──────────┬──────────┬──────────┬──────────┬───────────┐  │
│  │ 协议注册 │ 连接管理 │ 配置存储 │ 网关引擎 │ 日志/事件 │  │
│  └──────────┴──────────┴──────────┴──────────┴───────────┘  │
├─────────────────────────────────────────────────────────────┤
│                   协议 SDK（契约接口）                        │
│  ProtocolInterface │ ConnectorInterface │ DriverInterface   │
│  DataPointInterface│ GatewayRuleInterface│ HealthCheck      │
├─────────────────────────────────────────────────────────────┤
│              协议包（SDK 实现）                               │
│   modbus-pkg   │  opcua-pkg  │  profinet-pkg  │  bacnet-pkg │
│   ethernetip-pkg  │  ...  │   （第三方社区包）               │
└─────────────────────────────────────────────────────────────┘
```

### 包依赖关系（单向）

```
protocol-modbus ──┐
protocol-opcua ───┼──→ industrial-protocols-kernel
protocol-bacnet ──┘              ↑
                  user-app ──────┘
```

---

## 3. 核心原则

1. **内核（`industrial-protocols-kernel`）** — 薄契约层 + 服务容器。不包含任何协议实现。定义协议是什么、如何注册、如何被发现、如何配置。

2. **协议 SDK** — 一组 PHP 接口。每个协议包必须实现这些接口。这是内核与协议实现之间的唯一契约。

3. **框架适配器** — 内置于内核中。通过 Composer 的 `installed.json` 检测运行时环境，自动注册 ServiceProvider / ConfigProvider / ConfigPlugin。

4. **协议包** — 依赖 `industrial-protocols-kernel` 的独立 composer 包。实现 SDK 接口，通过协议注册器自动注册。

5. **网关引擎** — 内置于内核中。协议无关的基于规则链的转换引擎。输入/输出通过 SDK 接口解耦。

6. **用户安装:** `composer require industrial-protocols-kernel industrial-protocols-modbus` — 内核通过注册器发现已安装的协议包并自动注册。

---

## 4. 协议 SDK 契约

### 核心接口

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

### 网关接口

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

### 内核组件

| 组件 | 职责 |
|-----------|------|
| `ProtocolRegistry` | 发现已安装的协议包，管理 ProtocolInterface 实例 |
| `ConnectionManager` | 按协议+配置创建/缓存/销毁 ConnectorInterface 实例 |
| `ConfigRepository` | 统一的配置读写接口，默认基于文件，可切换数据库 |

---

## 5. 连接管理器

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

### 连接生命周期

```
                    ┌──────────────┐
        connect() → │  CONNECTING  │ → (失败) → FAULT → 重试? → CONNECTING
                    └──────┬───────┘
                           ↓ (成功)
                    ┌──────────────┐
                    │   CONNECTED  │ ← → disconnect()
                    └──────┬───────┘
                           ↓ (检测到错误)
                    ┌──────────────┐
                    │   DEGRADED   │ → (恢复) → CONNECTED
                    └──────┬───────┘    (失败)  → FAULT
                           ↓
                    ┌──────────────┐
                    │    FAULT     │ → 重试 → CONNECTING
                    └──────┬───────┘
                           ↓ (超过最大重试次数)
                    ┌──────────────┐
                    │    CLOSED    │
                    └──────────────┘
```

### 连接策略

| 策略 | 行为 | 适用场景 |
|----------|----------|----------|
| `EAGER` | 启动时建立所有连接 | 少量设备，延迟敏感 |
| `LAZY`（默认） | 首次读写时连接 | 大量设备，间歇访问 |
| `POOLED` | 预建连接池，复用连接 | 高频轮询，网关 |

### 健康检查

- 每个 `ConnectorInterface` 实现自己的 `getHealth()`
- `ConnectionManager` 按可配置间隔（默认 30 秒）轮询所有连接
- `HealthStatus` 包含：状态枚举（`HEALTHY / DEGRADED / FAULT`）、延迟、最后错误、重试次数
- 状态变更触发 `ConnectionStateChangedEvent`

### 重连

- 可配置：最大重试次数（默认 3）、重试间隔（默认 1 秒）、退避策略（固定 / 指数）
- 重连成功：`DEGRADED → CONNECTED`，触发恢复事件
- 重试耗尽：`FAULT → CLOSED`，触发告警事件

---

## 6. 配置仓库

### 双层模型

```
第一层：设备连接配置（必须持久化）
  协议类型、IP/串口、端口、从站 ID、超时、重试参数...
  → 文件（简单）或数据库（复杂）

第二层：数据点映射（动态）
  寄存器地址、标签名、数据类型、读写权限、数据转换...
  → 使用相同的 Repository 接口
```

### 接口

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

### 内置实现

| 实现 | 存储方式 | 适用场景 |
|---------------|---------|----------|
| `FileConfigRepository`（默认） | PHP/JSON/YAML | ≤10 台设备，简单部署 |
| `DatabaseConfigRepository` | MySQL/SQLite/PG | 大量设备，运行时管理 |
| `EnvConfigRepository` | 环境变量 | Docker/K8s 容器化部署 |

### 配置文件示例

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

### 仓库切换

内核启动时检测：如果用户注入了 `DatabaseConfigRepository`，则使用数据库。否则使用 `FileConfigRepository`。切换是透明的 — 框架适配器在 ServiceProvider 中绑定具体实现。

---

## 7. 网关引擎

### 架构

```
┌──────────────────────────────────────────────────────────┐
│                     网关引擎                               │
│                                                          │
│  ┌─────────────┐   ┌──────────────┐   ┌─────────────┐   │
│  │ 规则加载器  │ → │ 规则调度器   │ → │ 规则执行器  │   │
│  │（从配置或   │   │（按规则间隔  │   │（读源数据    │   │
│  │  数据库）   │   │  调度）      │   │  → 转换      │   │
│  └─────────────┘   └──────────────┘   │  → 写目标）  │   │
│                                       └──────┬──────┘   │
│                                              ↓           │
│  ┌──────────────────────────────────────────────────┐    │
│  │              数据转换管道                          │    │
│  │  原始字节 → 解析 → 标准化 → 映射 → 编码          │    │
│  └──────────────────────────────────────────────────┘    │
│                                                          │
│  ┌──────────┐  ┌──────────┐  ┌────────────────────┐     │
│  │ 指标采集 │  │ 熔断器   │  │ 规则校验            │     │
│  │          │  │          │  │（类型兼容性检查）    │     │
│  └──────────┘  └──────────┘  └────────────────────┘     │
└──────────────────────────────────────────────────────────┘
```

### 规则模型

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

### 触发模式

| 模式 | 行为 |
|------|----------|
| `poll` | 固定间隔：读源 → 写目标 |
| `change` | 仅在源数据变化时写入（事件驱动） |
| `cron` | Cron 表达式（如 `*/5 * * * *`）批量同步 |

### 数据转换管道

```
Source Frame
  → Driver::send(read_frame)
    → Frame::getData()              // ['40001' => 23.5]
      → Transform callable?          // optional custom transform
        → Type coercion              // FLOAT32 → Double
          → Target Frame::fromData(['ns=1;s=Temperature' => 23.5])
            → Target Driver::send(write_frame)
```

`transform` 为空且类型兼容时 = 直接透传。类型强制转换处理跨类型映射（INT16 → FLOAT32 等）。复杂转换（线性缩放、字节序交换、多寄存器组装）使用用户提供的 callable。

### 熔断器

单条规则连续 N 次失败后自动暂停。冷却后进入半开状态重试。防止规则间级联故障。

---

## 8. 框架适配器 + 统一协程层

所有框架至少支持一种协程运行时。协程能力在内核层面统一处理，而非每个框架单独处理。

### 协程适配器（内核内置）

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

| 实现 | 运行时 | 检测方式 |
|---------------|---------|------------|
| `SwooleCoroutineAdapter` | Swoole | `extension_loaded('swoole') && Co::getCid() >= 0` |
| `SwowCoroutineAdapter` | Swow | `extension_loaded('swow')` |
| `FiberCoroutineAdapter` | PHP 8.1 Fiber | 纯 PHP，无需扩展 |
| `SyncCoroutineAdapter`（回退） | 无 | 同步阻塞 |

检测优先级：`Swoole → Swow → Fiber → Sync`

### 框架协程支持矩阵

| 框架 | Swoole | Swow | Fiber | FPM |
|-----------|--------|------|-------|-----|
| **Laravel** | Octane（Swoole） | — | Octane（Fiber） | 默认 |
| **Webman** | Swoole 事件驱动 | — | workerman 5.x | workerman 4.x |
| **Hyperf** | 原生 | 原生 | — | — |
| **ThinkPHP** | think-swoole | — | — | 默认 |
| **Yii2** | swoole-yii2 | — | — | 默认 |

### 框架适配器（简化版）

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

框架适配器职责（精简为）：**配置发布 + 容器绑定 + CLI 命令**。协程、连接池、异步 — 全部由内核的 `CoroutineAdapterInterface` 处理。

### 各框架适配器行为

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

### 回退方案：PlainPhpAdapter

```php
// No-framework fallback
$kernel = new Erikwang2013\IndustrialProtocols\Kernel([
    'config_path' => __DIR__ . '/industrial-protocols.php',
]);
$kernel->boot();
$conn = $kernel->getConnectionManager()->connect('plc-001');
$temp = $conn->read('40001');
```

### 自动发现流程

```
composer require industrial-protocols-kernel industrial-protocols-modbus
              │
              ▼
┌─────────────────────────────────────────────┐
│  Kernel::boot() 遍历 FrameworkAdapter[]     │
│                                             │
│  LaravelAdapter::detect()                   │
│    → class_exists('Illuminate\...\Application') ? │
│    → true → 注册，break                     │
│    → false ↓                                │
│  WebmanAdapter::detect()                    │
│    → class_exists('Workerman\Worker') ?     │
│    → true → 注册，break                     │
│    → ...                                    │
│  （无匹配）                                  │
│    → PlainPhpAdapter（回退）                │
└─────────────────────────────────────────────┘
```

### 协程对其他组件的影响

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

**DriverInterface 扩展:**
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

## 9. 日志与事件

基于 PSR-14 EventDispatcher。所有关键节点均触发事件。

### 事件目录

```
连接事件:
  ConnectionConnectedEvent       // 设备已连接
  ConnectionDisconnectedEvent    // 设备已断开
  ConnectionStateChangedEvent    // 状态变更（HEALTHY → DEGRADED → FAULT → CLOSED）
  ConnectionRetryEvent           // 重连尝试

数据事件:
  DataReadEvent                  // 读取完成（设备、点位、值、延迟）
  DataWriteEvent                 // 写入完成
  DataErrorEvent                 // 读写错误（设备、点位、错误信息、重试次数）

网关事件:
  GatewayRuleStartedEvent        // 规则执行开始
  GatewayRuleCompletedEvent      // 规则执行完成（源 → 目标 行数）
  GatewayRuleFailedEvent         // 规则执行失败
  GatewayCircuitBreakerEvent     // 熔断器打开 / 重置

系统事件:
  KernelBootedEvent              // 内核启动完成
  ProtocolRegisteredEvent        // 注册器发现新协议
```

### 日志驱动

```php
interface LogDriverInterface
{
    public function log(string $level, string $message, array $context = []): void;
    public function event(object $event): void;
}
```

| 实现 | 行为 |
|---------------|----------|
| `PsrLogDriver`（默认） | 委托给 PSR-3 Logger，兼容所有框架 |
| `NullLogDriver` | 静默丢弃所有日志 |
| `FileLogDriver` | 直接写入文件，无框架依赖 |

### 日志级别约定

| 场景 | 级别 | 示例 |
|----------|-------|---------|
| 连接/断开 | INFO | `Device plc-001 connected (Modbus TCP 192.168.1.10:502)` |
| 读/写 | DEBUG | `Read 40001-40010 from plc-001 (23ms)` |
| 重连中 | WARNING | `Reconnecting plc-001, attempt 2/3` |
| 读写失败 | ERROR | `Write 40001 failed: timeout after 3000ms` |
| 熔断器触发 | CRITICAL | `Gateway rule gw-001 breaker opened after 5 failures` |

### 事件驱动流程

```
ConnectionManager
    │
    ├── connect() ──→ ConnectionConnectedEvent
    │                     │
    │                     ├──→ LogDriver::event()  （写日志）
    │                     ├──→ UserListener        （自定义回调）
    │                     └──→ AlertChannel        （钉钉/邮件/Webhook）
    │
    ├── read() ──→ DataReadEvent
    │                 │
    │                 ├──→ MetricsCollector::record()
    │                 └──→ GatewayEngine 检查 change 触发规则
    │
    └── health() ──→ ConnectionStateChangedEvent
                        │
                        └──→ AlertChannel（若为 FAULT 状态）
```

用户通过 PSR-14 ListenerProvider 注册监听器。内核内置 `EventLoggerSubscriber`，将所有事件转化为日志。

---

## 10. 错误处理与重试策略

### 异常层次结构

```
IndustrialProtocolsException (RuntimeException)
├── ConnectionException
│   ├── ConnectionTimeoutException
│   ├── ConnectionRefusedException
│   ├── ConnectionClosedException
│   └── AuthenticationException
├── ProtocolException
│   ├── FrameException              // 非法帧格式
│   ├── CrcException                // 校验和不匹配
│   ├── UnsupportedVariantException
│   └── DataTypeException
├── DeviceException
│   ├── DeviceBusyException
│   ├── DeviceErrorException        // 设备返回错误码
│   └── AddressOutOfRangeException
└── GatewayException
    ├── RuleValidationException
    └── CircuitBreakerOpenException
```

### 重试策略

```php
interface RetryStrategyInterface
{
    public function shouldRetry(int $attempt, \Throwable $error): bool;
    public function getDelay(int $attempt): int;  // ms
}
```

| 策略 | 行为 | 适用场景 |
|----------|----------|-----|
| `NoRetryStrategy` | 不重试 | 写入（默认，避免幂等风险） |
| `FixedRetryStrategy` | 固定间隔，N 次 | 简单场景 |
| `ExponentialBackoffStrategy`（默认） | 1s → 2s → 4s → 8s... | 读取、连接建立 |
| `JitteredBackoffStrategy` | 指数 + 随机抖动 | 多设备，防止惊群效应 |

### 分层重试

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

### 错误与异常映射表

| 场景 | 异常 | 是否重试？ | 事件 |
|----------|-----------|--------|-------|
| TCP 连接超时 | `ConnectionTimeoutException` | 是（最多 3 次） | `ConnectionRetryEvent` |
| 帧 CRC 错误 | `CrcException` | 是（最多 1 次） | `DataErrorEvent` |
| 地址超出范围 | `AddressOutOfRangeException` | 否 | `DataErrorEvent` |
| 设备忙 | `DeviceBusyException` | 是（带延迟） | `DataErrorEvent` |
| 写入失败 | `ProtocolException` | 否（默认） | `DataErrorEvent` |
| 熔断器打开 | `CircuitBreakerOpenException` | 否 | `GatewayCircuitBreakerEvent` |

### 错误投递

双通道同时进行：

1. **异常** — 同步抛出，调用方可 `try/catch`
2. **事件** — `DataErrorEvent` / `ConnectionStateChangedEvent`，异步监听

---

## 11. 设计章节（待续）

- [x] 协议 SDK 契约详情
- [x] 连接管理器设计
- [x] 配置仓库设计
- [x] 网关引擎设计
- [x] 框架适配器设计
- [x] 日志与事件设计
- [x] 错误处理与重试策略
- [x] 测试策略
- [x] 包命名与仓库结构
- [x] 实施分阶段/路线图

---

## 12. 实施分阶段

### 第一阶段：内核 + Modbus（MVP）

| 任务 | 内容 |
|------|---------|
| 内核骨架 | `Kernel::boot()`、ProtocolRegistry、ConfigRepository（文件）、LogDriver（PSR）、Event |
| SDK 接口 | ProtocolInterface、ConnectorInterface、DriverInterface、FrameInterface、DataPointInterface |
| 协程层 | CoroutineAdapterInterface + FiberAdapter + SyncAdapter（Swoole/Swow 后续） |
| ConnectionManager | Lazy + Eager 策略，健康检查，重连，异常层次结构 |
| 框架适配器 | PlainPhpAdapter + 框架检测链 |
| Modbus TCP | 纯 PHP socket，帧编解码，保持/输入/线圈寄存器 |
| Modbus RTU | 串口通信（ext-dio 或 FFI） |
| 测试 | Modbus Mock Server + Connector 模拟 ≥80% |
| 文档 | README、快速入门、Modbus 配置示例 |

### 第二阶段：协议扩展 + 网关

| 任务 | 内容 |
|------|---------|
| OPC UA | 客户端模式，二进制协议栈，安全策略 |
| 网关引擎 | 完整实现：规则加载器/调度器/执行器，poll/change/cron，熔断器 |
| 协程增强 | SwooleAdapter + SwowAdapter，PooledStrategy |
| 配置 | DatabaseConfigRepository（MySQL/SQLite） |
| Laravel 适配器 | ServiceProvider + Facade + artisan 命令 + 配置发布 |
| Webman 适配器 | config/plugin 自动发现 |
| 测试 | OPC UA Mock Server，网关规则模拟 |

### 第三阶段：更多协议 + 更多框架

| 任务 | 内容 |
|------|---------|
| BACnet IP | Who-Is/I-Am、ReadProperty、WriteProperty |
| EtherNet/IP | CIP 协议栈，Class 1/3 连接 |
| Profinet | 实时 + 非实时通道（可能需要 FFI/C 桥接） |
| Hyperf 适配器 | ConfigProvider + DI + 协程/连接池深度集成 |
| ThinkPHP 适配器 | services.php 自动发现 |
| Yii2 适配器 | Bootstrap + DI Container |

### 第四阶段：生产就绪

| 任务 | 内容 |
|------|---------|
| E2E 测试 | 基于 Docker 的协议模拟器 |
| 性能测试 | 并发读写，批量点位扫描，网关吞吐量 |
| 指标监控 | MetricsCollector + Prometheus 导出 |
| 告警通道 | 钉钉/飞书/邮件/Webhook |
| 文档 | 协议 API 文档，框架集成指南，网关配置指南 |
| 安全审计 | OPC UA 证书管理，Modbus 安全，输入验证 |

### 阶段依赖关系

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

## 13. 附录：完整需求追溯

| # | 问题 | 决策 |
|---|----------|----------|
| 1 | 协议范围 | 所有主流协议：Modbus、Profinet、EtherNet/IP、OPC UA、BACnet |
| 2 | 核心用例 | 数据采集 + 设备控制 + 协议网关/转换 |
| 3 | 异步支持 | 同步优先；由框架适配器处理协程/Fiber 适配 |
| 4 | PHP 版本 | ≥ 8.1（Fiber、枚举、只读属性） |
| 5 | 协议实现 | 简单协议纯 PHP socket；复杂协议 FFI 或桥接 |
| 6 | 框架集成 | 单包自动发现，检测运行时，即插即用 |
| 7 | 配置管理 | 默认基于文件 + Repository 接口支持数据库存储配置 |
| 8 | 测试策略 | TDD 优先（协议模拟测试覆盖率 ≥80%），逐步扩展到完整 E2E |
| 9 | 架构模式 | 微内核 + 协议 SDK（方案 C） |
| 10 | 协程支持 | 所有框架支持 Swoole；内核统一协程层 |
| 11 | 网关触发模式 | poll、change、cron |
| 12 | 错误投递 | 异常（同步）+ 事件（异步）双通道 |
| 13 | 连接策略 | EAGER、LAZY、POOLED |
| 14 | 重试退避 | 指数加抖动（默认）、固定、不重试 |
