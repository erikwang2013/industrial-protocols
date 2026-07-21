# Industrial Protocols PHP

PHP 工业网络通信协议插件 —— 微内核 + 协议 SDK 架构，支持 Modbus、BACnet、EtherNet/IP 等主流工业协议，兼容 Plain PHP、Laravel、Webman、Hyperf、ThinkPHP、Yii2 六大运行环境。

> [English](README.en.md)

---

## 目录

- [设计说明](#设计说明)
- [架构](#架构)
  - [总体架构图](#总体架构图)
  - [包依赖关系（单向）](#包依赖关系单向)
  - [连接生命周期](#连接生命周期)
  - [网关引擎数据流](#网关引擎数据流)
  - [数据变换管道](#数据变换管道)
  - [异常层次](#异常层次)
  - [目录结构](#目录结构)
  - [Bridge Layer 架构](#bridge-layer-架构)
  - [协议实现方式对照](#协议实现方式对照)
- [功能清单](#功能清单)
- [参考手册](#参考手册)
  - [需求汇总](#需求汇总)
  - [连接策略详解](#连接策略详解)
  - [内置实现一览](#内置实现一览)
  - [框架接入机制](#框架接入机制)
  - [协程支持矩阵](#协程支持矩阵)
  - [日志级别约定](#日志级别约定)
  - [重试策略对照](#重试策略对照)
  - [异常对照表](#异常对照表)
  - [完整能力矩阵](#完整能力矩阵)
- [厂商适配](#厂商适配)
- [支持的工业通信协议](#支持的工业通信协议)
- [支持的框架](#支持的框架)
- [快速开始](#快速开始)
- [使用说明](#使用说明)
- [协议使用示例](#协议使用示例)
- [框架集成示例](#框架集成示例)
- [网关引擎](#网关引擎)
- [监控与告警](#监控与告警)
- [配置参考](#配置参考)
- [文档](#文档)
- [系统要求](#系统要求)
- [License](#license)

---

## 设计说明

### 为什么是微内核？

工业通信协议种类繁多（Modbus、BACnet、OPC UA、Profinet、EtherNet/IP...），每个协议又有多种变体（TCP/RTU/ASCII、Client/Server）。如果将所有协议编码在一个包里，会导致：

- **包体积膨胀** — 用户只需 Modbus 却要安装全部协议
- **协议间耦合** — 一个协议的 bug 修复迫使全局发版
- **扩展困难** — 第三方想贡献新协议需要修改核心代码

微内核方案将系统拆分为两层：

```
┌─────────────────────────────────────────────────┐
│  协议层（可变）                                    │
│  modbus-pkg · bacnet-pkg · ethernetip-pkg · ...  │
│  每个协议是独立的 Composer 包，遵守统一 SDK 合约      │
├─────────────────────────────────────────────────┤
│  内核层（稳定）                                    │
│  industrial-protocols-kernel                     │
│  连接管理 · 配置管理 · 网关引擎 · 事件/日志/协程       │
│  SDK 接口 · 框架适配 · 监控告警                     │
└─────────────────────────────────────────────────┘
```

**内核只定义「协议是什么」，不包含任何具体的协议实现。** 协议包通过实现 SDK 接口接入，用户按需安装。

### 协议 SDK 合约

所有协议包必须实现的 6 个核心接口：

```php
interface ProtocolInterface    // 协议身份：名称、版本、变体、创建连接器
interface ConnectorInterface   // 设备连接：connect / disconnect / read / write / health
interface DriverInterface      // 底层通信：send frame → receive frame
interface FrameInterface       // 协议帧：toBytes / fromBytes / getData
interface DataPointInterface   // 数据点位：地址、类型、访问权限
interface GatewayRuleInterface // 网关规则：源 → 目标映射 + 转换函数
```

协议包只需依赖 kernel，实现上述接口后，通过 composer.json 的 `extra` 字段声明协议类：

```json
{
    "extra": {
        "industrial-protocols": {
            "protocol": "Erikwang2013\\IndustrialProtocols\\Modbus\\ModbusProtocol"
        }
    }
}
```

Kernel 启动时自动扫描已安装包的 `extra` 字段，发现并注册协议，用户零配置。

### 框架适配策略

每个 PHP 框架有不同的服务容器、配置加载、命令行机制。内核通过 `FrameworkAdapterInterface` 统一抽象：

```php
interface FrameworkAdapterInterface
{
    public function detect(): bool;               // 检测当前是否该框架
    public function getName(): string;            // 框架名称
    public function registerConfig(): void;       // 注册/发布配置
    public function registerServices(): void;     // 注册容器绑定
    public function registerCommands(): void;     // 注册 CLI 命令
    public function getConfigPath(): string;      // 配置文件路径
    public function isLongRunning(): bool;        // 是否常驻进程
}
```

Kernel 启动时按优先级遍历所有 Adapter，第一个 `detect()` 返回 true 的即命中。未命中任何框架时回退到 PlainPhpAdapter。

### 协程统一抽象

PHP 生态中有多种协程运行时（Swoole、Swow、Fiber），各自 API 不同。内核提供统一的 `CoroutineAdapterInterface`：

```php
interface CoroutineAdapterInterface
{
    public function isAvailable(): bool;
    public function create(callable $fn): mixed;        // 创建协程
    public function sleep(float $seconds): void;        // 协程休眠
    public function parallel(array $callables): array;  // 并发执行
}
```

探测优先级：`Swoole → Swow → Fiber → Sync`。上层组件（ConnectionManager、GatewayEngine）通过该接口实现协程无关的逻辑。

---

## 架构

### 总体架构图

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
│  Modbus    │  BACnet/IP   │ EtherNet/IP │  OPC UA     │
│  Profinet  │  EtherCAT*   │ POWERLINK*  │ SERCOS III* │
│  pure PHP  │  UDP socket  │ TCP ENIP    │ UA Binary   │
│  *via bridge│*via bridge  │*via bridge  │*via bridge  │
└─────────────────────────────────────────────────────────────┘
```

### 包依赖关系（单向）

```
  protocol-modbus ──┐
  protocol-bacnet ──┼──→ industrial-protocols-kernel
  protocol-eip ────┘              ↑
                    user-app ─────┘
```

用户项目只需 `composer require industrial-protocols/kernel industrial-protocols/modbus`，kernel 自动发现已安装的协议包。

### 连接生命周期

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

### 网关引擎数据流

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

### 数据变换管道

```
Source Frame (raw bytes)
  → Driver::send(read_frame)
    → Frame::fromBytes(response)
      → Frame::getData()              // 提取结构化数据
        → Transform callable?          // 可选自定义转换（缩放、单位换算...）
          → Target Frame::toBytes()    // 构造目标协议帧
            → Target Driver::send()    // 写入目标设备
```

### 异常层次

```
IndustrialProtocolsException (RuntimeException)
├── ConnectionException
│   ├── ConnectionTimeoutException     — TCP 连接超时
│   ├── ConnectionRefusedException     — 连接被拒绝
│   └── ConnectionClosedException      — 连接已关闭
├── ProtocolException
│   ├── FrameException                  — 帧格式非法
│   └── CrcException                    — 校验码不匹配
├── DeviceException
│   ├── DeviceBusyException             — 设备忙
│   └── AddressOutOfRangeException      — 地址越界
└── GatewayException
    ├── RuleValidationException         — 规则校验失败
    └── CircuitBreakerOpenException     — 熔断器开启
```

### 目录结构

```
industrial-protocols/
├── packages/
│   ├── kernel/                         # 微内核
│   │   ├── src/
│   │   │   ├── Kernel.php              # 启动入口
│   │   │   ├── Protocol/               # SDK 接口 + ProtocolRegistry
│   │   │   ├── Connection/             # ConnectionManager + 连接策略
│   │   │   ├── Config/                 # 配置 Repository 接口与实现
│   │   │   ├── Gateway/                # GatewayEngine + CircuitBreaker
│   │   │   ├── Coroutine/              # 协程适配器 + 工厂
│   │   │   ├── Framework/              # 框架适配器 + 各框架集成代码
│   │   │   ├── Event/                  # 事件类
│   │   │   ├── Log/                    # 日志驱动
│   │   │   ├── Retry/                  # 重试策略
│   │   │   ├── Metrics/                # 指标采集
│   │   │   ├── Alert/                  # 告警通道
│   │   │   ├── Security/               # 输入校验
│   │   │   └── Exception/              # 异常层次
│   │   ├── config/                     # 默认配置模板
│   │   └── tests/
│   ├── modbus/                         # Modbus 协议包
│   │   ├── src/
│   │   │   ├── ModbusProtocol.php
│   │   │   ├── ModbusConnector.php
│   │   │   ├── Driver/                 # TCP 驱动
│   │   │   ├── Frame/                  # 帧编解码 + CRC16
│   │   │   └── Exception/
│   │   └── tests/
│   ├── bacnet/                         # BACnet/IP 协议包
│   └── ethernetip/                     # EtherNet/IP 协议包
├── docker/                             # Docker 模拟器
├── docs/                               # 设计文档 + 使用指南
├── tests/                              # 集成测试 + E2E 测试
├── composer.json                       # 根 monorepo 配置
└── phpunit.xml
```

### Bridge Layer 架构

Bridge 层桥接 PHP 应用与需要专用硬件的工业协议，通过统一接口适配厂商 C/C++ SDK 和网关设备。

```
┌──────────────────────────────────────────────────────────────┐
│                    Protocol Packages                          │
│  EtherCAT · POWERLINK · SERCOS III · Profinet RT · TSN       │
│  (通过 BridgeConnector 实现 ConnectorInterface)                 │
├──────────────────────────────────────────────────────────────┤
│                     BridgeConnector                           │
│  实现 ConnectorInterface，内部委托给 BridgeInterface             │
├──────────────────────────────────────────────────────────────┤
│                     BridgeInterface                           │
│  open() · close() · execute(command, data) · isReady()       │
├─────────────────────┬────────────────────────────────────────┤
│ ExternalProcessBridge│         TcpGatewayBridge                │
│ ┌─────────────────┐  │  ┌──────────────────────────────────┐  │
│ │ C/C++ SDK 子进程  │  │  │ 网关硬件 (Anybus/Hilscher/netX)   │  │
│ │ proc_open()      │  │  │ stream_socket_client()           │  │
│ │ stdin/stdout     │  │  │ TCP/UDP                          │  │
│ └────────┬────────┘  │  └───────────────┬──────────────────┘  │
│          │            │                  │                      │
│   ┌──────▼──────┐     │     ┌───────────▼──────────┐          │
│   │ TwinCAT ADS  │     │     │ Anybus · netX · MGate│          │
│   │ openPOWERLINK│     │     │ ctrlX CORE · AXL F   │          │
│   │ SOEM (EtherCAT)│   │     │ S7-1500 · IndraDrive │          │
│   └─────────────┘     │     └──────────────────────┘          │
│   (本地 C/C++ SDK)    │     (远程/嵌入式网关硬件)               │
└─────────────────────┴────────────────────────────────────────┘
                              │
                    ┌─────────▼─────────┐
                    │  VendorBridgeFactory │
                    │  8 厂商预置配置      │
                    │  create(vendor,      │
                    │    device, version)  │
                    └─────────────────────┘
```

| 桥接方式 | 类 | 通信方式 | 适用场景 |
|---------|------|---------|---------|
| 外部进程 | `ExternalProcessBridge` | `proc_open` stdin/stdout | 本地 C/C++ SDK（Beckhoff TwinCAT、openPOWERLINK） |
| 网关硬件 | `TcpGatewayBridge` | TCP/UDP Socket | 远程网关设备（Hilscher netX、HMS Anybus、Moxa MGate） |

### 协议实现方式对照

```
┌─────────────────────────────────────────────────────────┐
│  纯 PHP 实现（应用层协议）                                │
│  ┌──────────┬──────────┬──────────┬──────────────────┐  │
│  │ Modbus   │ BACnet   │ EIP      │ OPC UA           │  │
│  │ (TCP/RTU)│ (UDP)    │ (TCP)    │ (UA Binary/TCP)  │  │
│  │ Profinet │ HART     │ CC-Link  │                  │  │
│  │ (NRT)    │ (FSK)    │ (RS-485) │                  │  │
│  └──────────┴──────────┴──────────┴──────────────────┘  │
│  标准 Socket 通信，pure PHP 完整实现协议栈                │
├─────────────────────────────────────────────────────────┤
│  Bridge 桥接（需专用硬件）                                │
│  ┌──────────┬──────────┬──────────┬──────────────────┐  │
│  │ EtherCAT │ POWERLINK│ SERCOS   │ Profinet RT      │  │
│  │ (ESC芯片)│ (openMAC)│ (FPGA IP)│ (ERTEC)          │  │
│  │ TSN      │          │          │                  │  │
│  │ (TSN网卡)│          │          │                  │  │
│  └──────────┴──────────┴──────────┴──────────────────┘  │
│  硬件层协议，通过 BridgeInterface 适配厂商 SDK/网关      │
└─────────────────────────────────────────────────────────┘
```

---

## 功能清单

### 内核

| 功能 | 说明 |
|------|------|
| SDK 接口 | 6 个标准接口（Protocol / Connector / Driver / Frame / DataPoint / GatewayRule），第三方可基于接口开发新协议包 |
| 协议注册 | ProtocolRegistry 自动扫描 Composer 安装的协议包，零配置加载 |
| 连接管理 | 3 种策略 — Lazy（按需连接）、Eager（启动即连）、Pooled（连接池），支持健康检查、自动重连 |
| 配置管理 | 3 种实现 — FileConfigRepository（PHP 文件）、DatabaseConfigRepository（PDO/SQLite/MySQL）、EnvConfigRepository |
| 协程适配 | Swoole → Fiber → Sync 三级自动降级，框架选择最佳的协程运行时 |
| 事件系统 | 13 个事件类型，基于 PSR-14 EventDispatcher，支持自定义监听器 |
| 日志驱动 | 3 种实现 — PsrLogDriver（委托 PSR-3）、FileLogDriver（直接写文件）、NullLogDriver（关闭日志） |
| 重试策略 | 4 种策略 — NoRetry、FixedRetry、ExponentialBackoff、ExponentialBackoff + Jitter |
| 异常体系 | 20+ 分层异常：Connection / Protocol / Device / Gateway，附带上下文信息 |
| 框架适配 | 6 个框架 + 纯 PHP，安装即用，内核自动检测运行环境 |
| 硬件桥接 | BridgeInterface + ExternalProcessBridge + TcpGatewayBridge，适配 C/C++ SDK 和网关硬件 |
| 厂商适配 | 12 大厂商预置配置（Beckhoff/Siemens/B&R/Bosch Rexroth/Hilscher/HMS/Moxa/Phoenix Contact/Bihl+Wiedemann/ifm electronic/Pepperl+Fuchs/Softing），VendorBridgeFactory 一键创建桥接 |

### 网关引擎

| 功能 | 说明 |
|------|------|
| 规则引擎 | 支持 poll（定时轮询）、change（变化触发）、cron（定时表达式）三种触发模式 |
| 数据管道 | Source Frame → Parse → Transform → Encode → Target Frame，支持自定义转换函数 |
| 熔断器 | CLOSED → OPEN → HALF_OPEN 状态机，防止级联故障，可配置阈值和冷却时间 |
| 并发执行 | 协程环境下多规则并行执行，FPM 环境顺序执行 |

### 监控与安全

| 功能 | 说明 |
|------|------|
| 指标采集 | Counter / Gauge / Histogram，支持 Prometheus 文本格式导出 |
| 告警通道 | AlertManager + Webhook / Log 通道，多通道同时推送 |
| 输入校验 | InputValidator：设备 ID、主机地址、端口号、寄存器地址、帧大小、超时范围 |

---

## 参考手册

### 需求汇总

项目 14 项关键架构决策汇总：

| # | 决策项 | 决策 |
|---|--------|------|
| 1 | 协议范围 | Modbus、Profinet、EtherNet/IP、OPC UA、BACnet 全覆盖 |
| 2 | 核心场景 | 数据采集 + 设备控制 + 协议网关/转换 |
| 3 | 异步支持 | 同步为主，内核协程层统一适配 |
| 4 | PHP 版本 | >= 8.1（Fiber、枚举、readonly） |
| 5 | 协议实现 | 简单协议纯 PHP Socket，复杂协议 FFI 或桥接 |
| 6 | 框架集成 | 单包自动发现，检测运行环境即插即用 |
| 7 | 配置管理 | 文件默认 + Repository 接口可切数据库 |
| 8 | 测试策略 | TDD 先行，协议仿真测试 >=80%，逐步补 E2E |
| 9 | 架构模式 | 微内核 + 协议 SDK（方案 C） |
| 10 | 协程支持 | 所有框架均支持 Swoole，内核统一协程层 |
| 11 | 网关触发 | poll（轮询）、change（变化触发）、cron（定时表达式） |
| 12 | 错误传递 | 异常（同步）+ 事件（异步）双通道 |
| 13 | 连接策略 | EAGER（启动即连）、LAZY（按需连接）、POOLED（连接池） |
| 14 | 重试退避 | 指数退避 + 随机抖动（默认）、固定间隔、不重试 |

### 连接策略详解

| 策略 | 类名 | 行为 | 适用场景 | FPM | 常驻进程 |
|------|------|------|---------|-----|---------|
| LAZY（默认） | `LazyStrategy` | 首次 read/write 时才建立连接，缓存复用 | 设备多、间歇访问 | 推荐 | — |
| EAGER | `EagerStrategy` | boot() 时立即建立所有配置的设备连接 | 设备少、延迟敏感 | — | 推荐 |
| POOLED | `PooledStrategy` | 预建 N 个连接（默认4），轮询分配 | 高频轮询、网关 | — | 推荐 |

### 内置实现一览

| 组件 | 接口 | 内置实现 |
|------|------|---------|
| 配置仓库 | `ConfigRepositoryInterface` | `FileConfigRepository` / `DatabaseConfigRepository` / `EnvConfigRepository` |
| 协程适配 | `CoroutineAdapterInterface` | `SwooleCoroutineAdapter` / `FiberCoroutineAdapter` / `SyncCoroutineAdapter` |
| 日志驱动 | `LogDriverInterface` | `PsrLogDriver` / `FileLogDriver` / `NullLogDriver` |
| 重试策略 | `RetryStrategyInterface` | `NoRetryStrategy` / `FixedRetryStrategy` / `ExponentialBackoffStrategy` |
| 连接策略 | `StrategyInterface` | `LazyStrategy` / `EagerStrategy` / `PooledStrategy` |
| 告警通道 | `AlertChannelInterface` | `WebhookAlertChannel` / `LogAlertChannel` |
| 事件分发 | PSR-14 `EventDispatcherInterface` | 内置匿名实现 / 框架适配器注入 |

### 框架接入机制

| 框架 | 检测方式 | 配置机制 | 服务注册 | CLI 命令 | 协程支持 |
|------|---------|---------|---------|---------|---------|
| Plain PHP | 默认回退 | 直接指定 config_path | 手动 new Kernel | 无 | Fiber |
| Laravel | `Illuminate\Foundation\Application` | ServiceProvider::publishes() | Singleton + Facade | `industrial:connect` / `industrial:gateway:list` | Octane (Swoole) |
| Webman | `Workerman\Worker` | config/plugin/ 自动发现 | ProtocolProcess::onWorkerStart | 无 | Swoole Event Driver / Fiber |
| Hyperf | `Hyperf\Framework\ApplicationFactory` | ConfigProvider + config/autoload/ | KernelFactory DI 绑定 | `industrial:connect` / `gateway:list` | Swoole 原生 |
| ThinkPHP | `think\App` | services.php 自动发现 | IndustrialProtocolsService::boot() | 无 | think-swoole |
| Yii2 | `yii\base\Application` | Bootstrap + config/web.php | 应用组件注册 | 无 | swoole-yii2 |

### 协程支持矩阵

| 协程运行时 | 适配器类 | 探测方式 | 支持框架 | parallel() 实现 |
|-----------|---------|---------|---------|---------------|
| Swoole | `SwooleCoroutineAdapter` | `extension_loaded('swoole') && Co::getCid()>0` | Laravel / Webman / Hyperf / ThinkPHP / Yii2 | WaitGroup 并发 |
| Fiber | `FiberCoroutineAdapter` | `PHP_VERSION_ID >= 80100` | 所有框架 | Fiber::start() 顺序执行 |
| Sync（兜底） | `SyncCoroutineAdapter` | 永远可用 | 所有框架 | foreach 顺序执行 |

探测优先级：`Swoole -> Fiber -> Sync`

### 日志级别约定

| 级别 | 场景 | 示例 |
|------|------|------|
| DEBUG | 读写操作细节 | `Read 40001-40010 from plc-001 (23ms)` |
| INFO | 连接建立/断开 | `Device plc-001 connected (Modbus TCP 192.168.1.10:502)` |
| WARNING | 重连尝试 | `Reconnecting plc-001, attempt 2/3` |
| ERROR | 读写失败 | `Write 40001 failed: timeout after 3000ms` |
| CRITICAL | 熔断触发 | `Gateway rule gw-001 circuit breaker OPENED after 5 failures` |

### 重试策略对照

| 策略 | maxAttempts | delay (1st/2nd/3rd) | jitter | 适用场景 |
|------|------------|---------------------|--------|---------|
| `NoRetryStrategy` | 0 | 0 / 0 / 0 | — | 写操作默认（幂等风险）、不可重试异常 |
| `FixedRetryStrategy` | 3 | 1000 / 1000 / 1000ms | — | 简单重试，固定间隔 |
| `ExponentialBackoffStrategy`（默认） | 3 | 1000 / 2000 / 4000ms | 可选 | 读操作、连接建立 |
| ExponentialBackoff + Jitter | 3 | 500~1500 / 1000~3000 / 2000~6000ms | 强制随机 | 多设备场景防雷群效应 |

### 异常对照表

| 异常类 | 层次 | 触发条件 | 可重试？ | 触发事件 |
|--------|------|---------|--------|---------|
| `ConnectionTimeoutException` | Connection | TCP 连接超时 | 是（最多3次） | `ConnectionRetryEvent` |
| `ConnectionRefusedException` | Connection | 设备拒绝连接 | 是（最多3次） | `ConnectionRetryEvent` |
| `ConnectionClosedException` | Connection | 连接意外关闭 | 是 | `ConnectionStateChangedEvent` |
| `FrameException` | Protocol | 帧格式非法 | 否 | `DataErrorEvent` |
| `CrcException` | Protocol | CRC/校验码不匹配 | 是（最多1次） | `DataErrorEvent` |
| `DeviceBusyException` | Device | 设备返回忙信号 | 是（带延迟） | `DataErrorEvent` |
| `AddressOutOfRangeException` | Device | 地址超过有效范围 | 否 | `DataErrorEvent` |
| `CircuitBreakerOpenException` | Gateway | 熔断器开启 | 否 | `GatewayCircuitBreakerEvent` |
| `RuleValidationException` | Gateway | 网关规则校验失败 | 否 | `GatewayRuleFailedEvent` |

### 完整能力矩阵

| 能力 | Plain PHP | Laravel | Webman | Hyperf | ThinkPHP | Yii2 |
|------|----------|---------|--------|--------|----------|------|
| 框架检测 | 兜底 | Application 类 | Worker 类 | ApplicationFactory | think\App | yii\base\Application |
| 配置发现 | 手动指定 | artisan vendor:publish | config/plugin 自动 | ConfigProvider | services.php | Bootstrap |
| CLI 命令 | — | ✅ industrial:connect / gateway:list | — | ✅ connect / gateway:list | — | — |
| Facade/快捷访问 | Kernel 实例 | IndustrialProtocolsFacade | 无 | DI Container | 静态 singleton | Yii 组件 |
| Swoole 协程 | ✅ SwooleAdapter | ✅ Octane | ✅ Event Driver | ✅ 原生 | ✅ think-swoole | ✅ swoole-yii2 |
| Fiber 协程 | ✅ | ✅ Octane | ✅ workerman 5.x | — | — | — |
| 常驻进程 | — | ✅ Octane | ✅ | ✅ | — | ✅ swoole-yii2 |
| 连接池 | ✅ PooledStrategy | ✅ | ✅ | ✅ | — | ✅ |
| 网关引擎 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 熔断器 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 监控指标 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 告警通道 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 输入校验 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 数据库配置 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| 硬件桥接 | ✅ Bridge | ✅ Bridge | ✅ Bridge | ✅ Bridge | ✅ Bridge | ✅ Bridge |
| 厂商适配 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 厂商适配

内核内置 12 家主流工业硬件厂商的预置配置，无需手动查找 SDK 路径和端口号。

### 厂商列表

| 厂商 | 协议 | Bridge 类型 | 设备型号数 |
|------|------|-----------|----------|
| Beckhoff | EtherCAT | ExternalProcessBridge | 6 |
| Siemens | PROFINET | TcpGatewayBridge | 5 |
| B&R | POWERLINK | ExternalProcessBridge | 4 |
| Bosch Rexroth | SERCOS III | TcpGatewayBridge | 4 |
| Hilscher | 多协议 | TcpGatewayBridge | 4 |
| HMS/Anybus | 多协议 | TcpGatewayBridge | 4 |
| Moxa | 多协议 | TcpGatewayBridge | 4 |
| Phoenix Contact | PROFINET/EIP | TcpGatewayBridge | 4 |
| Bihl+Wiedemann | AS-Interface | TcpGatewayBridge | 2 |
| ifm electronic | IO-Link | TcpGatewayBridge | 2 |
| Pepperl+Fuchs | AS-i / HART | TcpGatewayBridge | 2 |
| Softing | FF / PROFIBUS | ExternalProcessBridge | 2 |

### 使用方式

```php
// 获取厂商工厂
$factory = $kernel->getVendorBridgeFactory();

// 列出所有支持的厂商（12 家）
$vendors = $factory->listVendors();

// 查看某厂商的设备型号
$devices = $factory->getDevices('siemens');
// → [S7-1200 V4.x, S7-1500 V3.x, ET 200SP V2.x, ET 200MP V2.x, S7-400 V6.x]

// 一键创建桥接 — 指定厂商、型号、版本
$bridge = $factory->create('beckhoff', 'CX2030', '3.1');
// 返回预配置的 ExternalProcessBridge，SDK 路径已自动填充

// 覆盖默认参数
$bridge = $factory->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
    'port' => 34964,
]);

// 连接并读取
$conn = new BridgeConnector($bridge, 'ethercat');
$conn->connect();
$result = $conn->read('0x6000:0x01');
```

### 配置合并优先级

```
厂商默认值 → 设备型号覆盖 → 用户自定义参数
```

参见 [厂商适配详细文档](docs/vendors.md)。

---

## 支持的工业通信协议

### 工业以太网协议

| 协议 | 阶段 | 变体 | 实现方式 | 支持操作 |
|------|------|------|---------|----------|
| **Modbus TCP** | Phase 1 | TCP | 纯 PHP Socket | FC 01/03/04/06/10 |
| **BACnet/IP** | Phase 3 | IP (UDP) | 纯 PHP UDP Socket | Who-Is/I-Am, ReadProperty |
| **EtherNet/IP** | Phase 3 | TCP | 纯 PHP Socket | ENIP 会话, CIP Read Tag |
| **OPC UA** | Phase 4 | Binary | 纯 PHP UA Binary 协议栈 | CreateSession, Read, Write, Browse |
| **Profinet NRT** | Phase 4 | NRT | 纯 PHP UDP/TCP | DCP 发现, Record Data 读写 |

### 现场总线协议

| 协议 | 变体 | 实现方式 | 说明 |
|------|------|---------|------|
| **Modbus RTU/ASCII** | RS-485 串口 | 纯 PHP 串口 | CRC16 校验, stty 串口配置 |
| **HART** | 4-20mA FSK | 纯 PHP 串口 | HART 调制解调器, PV/回路电流 |
| **CC-Link** | RS-485 | 纯 PHP 串口 | 主从轮询, CRC-16/XMODEM |
| **PROFIBUS** | DP / PA / FMS | Bridge | Siemens CP 5611 / Anybus / Hilscher |
| **CANopen** | CAN | Bridge | PCAN-USB / IXXAT / SocketCAN |
| **DeviceNet** | CAN | Bridge | Anybus DeviceNet Scanner |
| **Foundation Fieldbus** | H1 / HSE | Bridge | NI USB-8486 / Softing FFusb |
| **AS-Interface** | AS-i | Bridge | Bihl+Wiedemann / Pepperl+Fuchs |
| **IO-Link** | 点对点 | Bridge | ifm / Balluff IO-Link Master |
| **CC-Link IE** | 工业以太网 | Bridge | CC-Link IE Field 网关 |

### 需专用硬件的协议（Bridge）

| 协议 | 所需硬件 |
|------|---------|
| **EtherCAT** | ESC 芯片 (Beckhoff TwinCAT / SOEM) |
| **POWERLINK** | openMAC (openPOWERLINK / B&R) |
| **SERCOS III** | FPGA IP 核 (Bosch Rexroth / Hilscher) |
| **Profinet RT/IRT** | ERTEC 芯片 (Siemens / Hilscher) |
| **TSN** | TSN 网卡 (Intel I225 / NXP SJA1110) |
| **ControlNet** | 同轴令牌环接口 (Allen-Bradley) |
| **INTERBUS** | 环网接口 (Phoenix Contact) |
| **LonWorks** | Neuron 芯片 / 接口卡 |
| **WorldFIP** | FIP 总线接口 |
| **Lightbus** | 光纤接口 (Beckhoff) |
| **Modbus Plus** | 令牌环接口 (Schneider) |

---

## 支持的框架

| 框架 | 阶段 | 检测方式 | 协程支持 | 集成方式 |
|------|------|---------|---------|----------|
| **Plain PHP** | Phase 1 | 默认回退 | Fiber (PHP 8.1+) | 直接实例化 Kernel |
| **Laravel** | Phase 2 | `Illuminate\Foundation\Application` | Laravel Octane (Swoole) | ServiceProvider + Facade + artisan 命令 |
| **Webman** | Phase 2 | `Workerman\Worker` | Swoole Event Driver / Fiber | `config/plugin` 自动发现 + ProtocolProcess |
| **Hyperf** | Phase 3 | `Hyperf\Framework\ApplicationFactory` | Swoole 原生 | ConfigProvider + DI 容器深度整合 |
| **ThinkPHP** | Phase 3 | `think\App` | think-swoole | services.php 自动发现 + 单例服务 |
| **Yii2** | Phase 3 | `yii\base\Application` | swoole-yii2 | Bootstrap + 应用组件注册 |

框架检测优先级：`Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Plain PHP`

---

## 快速开始

### 安装

```bash
composer require industrial-protocols/kernel industrial-protocols/modbus
```

### 5 分钟上手

```php
<?php
require 'vendor/autoload.php';

use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

// 1. 创建配置
$config = __DIR__ . '/industrial-protocols.php';
file_put_contents($config, '<?php return ' . var_export([
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'variant'  => 'tcp',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
            'timeout'  => 3000,
        ],
    ],
    'gateway'      => ['rules' => []],
    'health_check_interval' => 30,
], true) . ';');

// 2. 启动内核
$kernel = new Kernel(['config_path' => $config]);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

// 3. 连接设备并读取数据
$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
echo "温度: " . $result['40001'] . "\n";

// 4. 写入数据
$conn->write(['40001' => 25]);

// 5. 健康检查
$health = $kernel->getConnectionManager()->health('plc-001');
echo "状态: " . $health->state->value . ", 延迟: " . $health->latencyMs . "ms\n";

$kernel->shutdown();
```

---

## 使用说明

### Kernel 生命周期

```
实例化 Kernel → register protocols → boot() → [使用] → shutdown()
```

- **实例化**后 protocol registry 即可用，注册协议包
- **boot()** 完成框架检测、配置加载、连接管理器初始化
- **shutdown()** 关闭所有设备连接

### ConnectionManager API

```php
$manager = $kernel->getConnectionManager();

// 连接设备（根据 strategy 决定连接时机）
$conn = $manager->connect('plc-001');

// 获取已有连接（不会触发连接建立）
$existing = $manager->getConnection('plc-001');

// 断开连接
$manager->disconnect('plc-001');

// 获取所有活跃连接
$all = $manager->getAllConnections();

// 单设备健康检查
$health = $manager->health('plc-001');

// 全部设备健康检查
$allHealth = $manager->healthAll();
```

### 连接策略对比

```php
// LAZY（默认）— FPM 环境推荐，首次读写时才连接
$kernel = new Kernel([
    'config_path' => $config,
]);

// EAGER — 常驻进程推荐，启动时建立所有连接
use Erikwang2013\IndustrialProtocols\Connection\Strategy\EagerStrategy;

// POOLED — 高频轮询/网关场景，预建连接池
use Erikwang2013\IndustrialProtocols\Connection\Strategy\PooledStrategy;
// poolSize=4 时，getOrCreate 以轮询方式从池中分配连接
```

### 重试配置

```php
// 配置文件
'default_retry_max'     => 3,
'default_retry_backoff' => 'exponential',  // exponential | fixed | none

// 程序化配置
use Erikwang2013\IndustrialProtocols\Retry\ExponentialBackoffStrategy;
use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;

$strategy = new ExponentialBackoffStrategy(
    maxAttempts: 5,
    baseDelayMs: 1000,
    jitter: true,                                        // 随机抖动防雷群
    retryableExceptions: [ConnectionTimeoutException::class],  // 仅此类异常触发重试
);
```

### 事件监听

```php
use Erikwang2013\IndustrialProtocols\Event\DataReadEvent;
use Erikwang2013\IndustrialProtocols\Event\ConnectionStateChangedEvent;

$dispatcher->listen(DataReadEvent::class, function (DataReadEvent $e) {
    echo "设备 {$e->deviceId} 读操作完成，延迟 {$e->latencyMs}ms\n";
});

$dispatcher->listen(ConnectionStateChangedEvent::class, function ($e) {
    if ($e->newStatus->state->value === 'FAULT') {
        // 触发告警
    }
});
```

---

## 协议使用示例

### Modbus TCP

```php
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('plc-001');

// 读取保持寄存器
$result = $conn->read('40001');           // 单寄存器 → ['40001' => 237]
$batch  = $conn->read(['40001', '40002']); // 批量读取

// 写入保持寄存器
$conn->write(['40001' => 100]);
$conn->write(['40001' => 200, '40002' => 300]);

// 地址格式
// 40001-49999  保持寄存器（Read/Write）
// 30001-39999  输入寄存器（Read Only）
// 0-9999       原始偏移
```

### BACnet/IP

```php
use Erikwang2013\IndustrialProtocols\Bacnet\BacnetProtocol;

$kernel->getProtocolRegistry()->register(new BacnetProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('bacnet-device');

// 发现网络中的 BACnet 设备（Who-Is 广播）
$devices = $conn->discoverDevices(5);  // 5 秒超时

// 读取属性: ObjectType:Instance:PropertyId
$result = $conn->read('0:1:85');       // AnalogInput 1, PresentValue
```

### EtherNet/IP

```php
use Erikwang2013\IndustrialProtocols\EtherNetIP\EtherNetIPProtocol;

$kernel->getProtocolRegistry()->register(new EtherNetIPProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('eip-plc');

// 读取 CIP 标签
$result = $conn->read('MyTagName');
```

### OPC UA Binary

```php
use Erikwang2013\IndustrialProtocols\OpcUa\OpcUaProtocol;

$kernel->getProtocolRegistry()->register(new OpcUaProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('opcua-server');

// 读取 CurrentTime 节点
$result = $conn->read('i=2258');

// 浏览地址空间
$children = $conn->browse('i=85');

// 写入节点
$conn->write(['ns=2;s=SetPoint' => 100.0]);
```

### Profinet NRT

```php
use Erikwang2013\IndustrialProtocols\Profinet\ProfinetProtocol;

$kernel->getProtocolRegistry()->register(new ProfinetProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('pn-device');

// DCP 设备发现
$devices = $conn->discoverDevices(5);

// 读取 Record Data（api:slot:subslot:index）
$result = $conn->read('0:0:1:0xAFF0');
```

### Modbus RTU (串口)

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
$pv = $conn->read('pv');           // 主变量
$current = $conn->read('loop_current'); // 回路电流 (mA)
```

### 硬件桥接协议

```php
use IndustrialProtocols\Bridge\ExternalProcessBridge;
use IndustrialProtocols\EtherCat\EtherCatProtocol;

// 通过 C/C++ SDK 桥接 EtherCAT
$bridge = new ExternalProcessBridge('/opt/ethercat-sdk/ecat_master');

$kernel->getProtocolRegistry()->register(new EtherCatProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('ethercat-device', [
    'protocol' => 'ethercat',
    'bridge'   => $bridge,
]);
$result = $conn->read('0x6000:0x01'); // CoE SDO 读取
```

---

## 框架集成示例

### Laravel

```bash
# 发布配置文件
php artisan vendor:publish --tag=industrial-protocols-config
```

```php
// app/Providers/AppServiceProvider.php
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

public function boot(): void
{
    $kernel = app(Kernel::class);
    $kernel->getProtocolRegistry()->register(new ModbusProtocol());
}

// 使用 Facade
use Erikwang2013\IndustrialProtocols\Framework\Laravel\IndustrialProtocolsFacade;

$result = IndustrialProtocolsFacade::connect('plc-001')->read('40001');
```

```bash
# Artisan 命令
php artisan industrial:connect plc-001
php artisan industrial:gateway:list
```

### Webman

Webman 通过 `config/plugin/` 目录自动发现插件，安装即用。

创建配置文件：

```php
// config/plugin/industrial-protocols/kernel/config/industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'variant'  => 'tcp',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
            'timeout'  => 3000,
        ],
    ],
    // ...
];
```

ProtocolProcess 在 Worker 启动时自动初始化 Kernel、注册协议包、建立连接。无需额外代码。

### Hyperf

配置自动通过 ConfigProvider 注入。创建配置文件：

```php
// config/autoload/industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
        ],
    ],
];
```

```php
// 控制器中使用
use Hyperf\Context\ApplicationContext;
use Erikwang2013\IndustrialProtocols\Kernel;

$kernel = ApplicationContext::getContainer()->get(Kernel::class);
$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
```

```bash
# Hyperf 命令
php bin/hyperf.php industrial:connect plc-001
php bin/hyperf.php industrial:gateway:list
```

### ThinkPHP

```php
// app/service.php 中添加
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;

// 任意位置使用
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;

$kernel = IndustrialProtocolsService::boot();
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

### Yii2

```php
// config/web.php
return [
    'bootstrap' => [
        'industrial-protocols' => \Erikwang2013\IndustrialProtocols\Framework\Yii2\Bootstrap::class,
    ],
    'components' => [
        'industrial-protocols' => [
            'class' => \Erikwang2013\IndustrialProtocols\Kernel::class,
        ],
    ],
];
```

```php
// 控制器中使用
$kernel = Yii::$app->get('industrial-protocols');
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

---

## 网关引擎

实现跨协议数据转发（如 Modbus → OPC UA）：

```php
use Erikwang2013\IndustrialProtocols\Gateway\GatewayEngine;
use Erikwang2013\IndustrialProtocols\Gateway\GatewayRule;

$engine = new GatewayEngine(
    $kernel->getConnectionManager(),
    $eventDispatcher,
    $kernel->getCoroutineAdapter(),
    $kernel->getLogDriver(),
);

// 规则：每 1000ms 读取 plc-001 的 40001，写入 opcua-server
$engine->addRule(new GatewayRule(
    id: 'modbus-to-opcua',
    sourceDevice: 'plc-001',
    sourcePoint:  '40001',
    targetDevice: 'opcua-server',
    targetPoint:  'ns=1;s=Temperature',
    transform:    fn($raw) => $raw / 10,  // 原始值除以 10
    trigger:      'poll',
    interval:     1000,                     // ms
));

// 执行一次
$result = $engine->executeOnce('modbus-to-opcua');

// 或持续运行（协程环境下多条规则并发执行）
$engine->run(tickIntervalMs: 100);
$engine->stop();
```

### 触发模式

| 模式 | 行为 | 适用场景 |
|------|------|---------|
| `poll` | 每隔 N ms 拉取源数据写入目标 | 持续采集显示 |
| `change` | 仅源数据变化时写入 | 报警、事件通知 |
| `cron` | 按 cron 表达式批量同步 | 定时报表 |

### 熔断器

单条规则连续失败 N 次后自动熔断，冷却时间到后半开试探：

```php
new GatewayRule(
    // ...
    failureThreshold: 5,      // 连续 5 次失败触发熔断
    cooldownSeconds: 30.0,    // 30 秒后尝试恢复
)
```

---

## 监控与告警

### 指标采集

```php
use Erikwang2013\IndustrialProtocols\Metrics\MetricsCollector;

$metrics = new MetricsCollector();

// 计数器 — 累计读写次数
$metrics->incrementCounter('reads_total', ['device' => 'plc-001']);
$metrics->incrementCounter('writes_total', ['device' => 'plc-001'], 5);

// 仪表盘 — 活跃连接数
$metrics->setGauge('active_connections', count($manager->getAllConnections()));

// 直方图 — 读取延迟分布
$metrics->observeHistogram('read_latency_ms', 15.2, ['device' => 'plc-001']);

// Prometheus 格式导出
header('Content-Type: text/plain');
echo $metrics->toPrometheus('industrial');
```

### 告警推送

```php
use Erikwang2013\IndustrialProtocols\Alert\AlertManager;
use Erikwang2013\IndustrialProtocols\Alert\WebhookAlertChannel;

$alert = new AlertManager();
$alert->addChannel('dingtalk', new WebhookAlertChannel('https://oapi.dingtalk.com/robot/send?...'));
$alert->addChannel('feishu',   new WebhookAlertChannel('https://open.feishu.cn/open-apis/bot/v2/hook/...'));

// 连接故障时推送
$alert->send('设备断连', 'plc-001 连接超时', level: 'critical');
```

---

## 配置参考

```php
<?php
// industrial-protocols.php
return [
    // 设备连接配置
    'devices' => [
        'plc-001' => [
            'protocol'  => 'modbus',        // 协议名称
            'variant'   => 'tcp',           // 协议变体
            'host'      => '192.168.1.10',  // 设备 IP 或串口
            'port'      => 502,             // 端口
            'unit_id'   => 1,               // 从站 ID
            'timeout'   => 3000,            // 超时 (ms)
            'strategy'  => 'lazy',          // 连接策略: lazy | eager | pooled
            'pool_size' => 4,               // 连接池大小（pooled 策略）
            'points'    => [                // 数据点位映射
                ['address' => '40001', 'name' => 'temperature', 'type' => 'FLOAT32', 'access' => 'RW'],
                ['address' => '40003', 'name' => 'pressure',    'type' => 'FLOAT32', 'access' => 'RO'],
            ],
        ],
    ],

    // 网关规则
    'gateway' => [
        'rules' => [
            [
                'id'             => 'gw-001',
                'source_device'  => 'plc-001',
                'source_point'   => '40001',
                'target_device'  => 'opcua-server',
                'target_point'   => 'ns=1;s=Temperature',
                'trigger'        => 'poll',    // poll | change | cron
                'interval'       => 1000,
            ],
        ],
    ],

    // 全局默认值
    'health_check_interval' => 30,          // 健康检查间隔 (s)
    'default_retry_max'     => 3,           // 最大重试次数
    'default_retry_backoff' => 'exponential', // 退避策略
    'default_timeout'       => 3000,        // 默认超时 (ms)
];
```

---

## 文档

- [协议 API 参考](docs/protocols.md) — Modbus、BACnet、EtherNet/IP 连接配置、读写操作、地址格式
- [框架集成指南](docs/framework-integration.md) — Plain PHP、Laravel、Webman、Hyperf、ThinkPHP、Yii2 集成详述
- [网关引擎指南](docs/gateway.md) — 规则配置、触发模式、熔断器、数据变换管道
- [安全指南](docs/security.md) — 输入校验、网络安全、异常参考
- [厂商适配参考](docs/vendors.md) — 12 大厂商的预置配置、设备型号、SDK 路径

---

## 系统要求

- PHP >= 8.1
- Composer
- 可选：ext-swoole（Swoole 协程加速）
- 可选：ext-pdo（数据库配置存储）
- 可选：C/C++ SDK 可执行文件（EtherCAT/POWERLINK 桥接）
- 可选：网关硬件（SERCOS III/Profinet RT/TSN 桥接）

---

## License

MIT
