# Industrial Protocols PHP

PHP 工业网络通信协议集 —— 微内核 + 协议 SDK 架构，覆盖 42 种工业协议，兼容 6 种 PHP 运行时环境。

> [English](README.en.md)

---

## 目录

- [项目简介](#项目简介)
- [架构概览](#架构概览)
- [支持的协议](#支持的协议)
- [支持的框架](#支持的框架)
- [快速开始](#快速开始)
- [核心功能](#核心功能)
- [协议使用示例](#协议使用示例)
- [框架集成示例](#框架集成示例)
- [厂商适配](#厂商适配)
- [配置参考](#配置参考)
- [文档链接](#文档链接)
- [系统要求](#系统要求)
- [License](#license)

---

## 项目简介

Industrial Protocols 是一个面向 PHP 生态的工业通信协议集，采用 **微内核 + 协议 SDK** 架构。内核提供连接管理、配置管理、网关引擎、事件系统、协程适配等基础设施，协议包作为独立 Composer 包按需安装，通过实现统一 SDK 接口接入内核。

**规模：** 42 个协议包（15 个纯 PHP 实现 + 27 个 Bridge 桥接），351 个测试用例，731 个断言；12 家厂商预置配置；6 种框架适配器；PHP >= 8.1。

**核心理念：** 内核只定义"协议是什么"，不包含任何具体协议实现。用户按需安装协议包，内核启动时自动发现并注册。每个协议包仅依赖内核，协议之间零耦合。

---

## 架构概览

```
┌──────────────────────────────────────────────────────────────┐
│                       User Application                        │
├──────────────────────────────────────────────────────────────┤
│   Framework Adapters: Laravel · Webman · Hyperf · ThinkPHP · Yii2 · Plain PHP
├──────────────────────────────────────────────────────────────┤
│                      Micro-Kernel                             │
│  ┌──────────┬──────────┬──────────┬──────────┬────────────┐  │
│  │ Protocol │Connection│  Config  │ Gateway  │ Events(13) │  │
│  │ Registry │ Manager  │Repository│ Engine   │ PSR-14     │  │
│  ├──────────┼──────────┼──────────┼──────────┼────────────┤  │
│  │Coroutine │  Retry   │  Alert   │ Metrics  │ Security   │  │
│  │(3-level) │ (4 types)│ Manager  │(Prometheus)│Validator  │  │
│  ├──────────┼──────────┼──────────┼──────────┼────────────┤  │
│  │  Vendor  │  Bridge  │  Logging │Exception │            │  │
│  │(12厂商)  │  Layer   │(PSR-3/File)|(20+种)  │            │  │
│  └──────────┴──────────┴──────────┴──────────┴────────────┘  │
├──────────────────────────────────────────────────────────────┤
│                Protocol SDK (6 Core Interfaces)               │
├──────────────────────────────────────────────────────────────┤
│  42 Protocol Packages: 15 Pure PHP + 27 Bridge               │
└──────────────────────────────────────────────────────────────┘
```

**关键设计决策：**

| 决策项 | 方案 |
|--------|------|
| 架构模式 | 微内核 + 协议 SDK，协议包独立安装、零耦合 |
| 连接策略 | Lazy（按需连接）、Eager（启动即连）、Pooled（连接池） |
| 协程适配 | Swoole → Fiber → Sync 三级自动降级 |
| 重试策略 | 不重试 / 固定间隔 / 指数退避 / 指数退避+Jitter |
| 配置管理 | FileConfig / DatabaseConfig (PDO) / EnvConfig 三种实现 |
| 事件系统 | 13 种事件类型，基于 PSR-14 标准 |
| 网关触发 | poll（轮询）、change（变化触发）、cron（定时表达式） |
| 硬件桥接 | ExternalProcessBridge（本地 SDK 子进程）+ TcpGatewayBridge（远程网关 TCP） |

---

## 支持的协议

### 工业以太网（5 个）

| 协议 | 变体 | 实现方式 | 支持操作 |
|------|------|---------|----------|
| Modbus TCP | TCP | 纯 PHP Socket | FC 01/03/04/06/10 |
| BACnet/IP | IP (UDP) | 纯 PHP UDP | Who-Is/I-Am, ReadProperty |
| EtherNet/IP | TCP | 纯 PHP Socket | ENIP 会话, CIP Read Tag |
| OPC UA | UA Binary/TCP | 纯 PHP UA Binary 协议栈 | CreateSession, Read, Write, Browse |
| Profinet NRT | NRT (UDP/TCP) | 纯 PHP Socket | DCP 发现, Record Data 读写 |

### 现场总线（12 个）

| 协议 | 变体 | 实现方式 | 说明 |
|------|------|---------|------|
| Modbus RTU/ASCII | RS-485 串口 | 纯 PHP 串口 | CRC16 校验 |
| HART | 4-20mA FSK | 纯 PHP 串口 | HART 调制解调器, PV/回路电流 |
| CC-Link RS-485 | RS-485 | 纯 PHP 串口 | 主从轮询, CRC-16/XMODEM |
| DNP3 | TCP/串口 | 纯 PHP | 电力自动化, Class 0 轮询 |
| IEC 61850 | MMS over TCP | 纯 PHP | 变电站自动化, IED 数据路径 |
| PROFIBUS DP/PA/FMS | RS-485/MBP | Bridge (Anybus/Siemens CP) | 需网关或接口卡 |
| CANopen | CAN | Bridge (PCAN/SocketCAN) | 需 CAN 接口 |
| DeviceNet | CAN | Bridge (Anybus) | 需 DeviceNet Scanner |
| Foundation Fieldbus | H1/HSE | Bridge (NI/Softing) | 需 FF 接口 |
| AS-Interface | AS-i | Bridge (Bihl+Wiedemann/P+F) | 需 AS-i 网关 |
| IO-Link | 点对点 | Bridge (ifm/Balluff) | 需 IO-Link Master |
| CC-Link IE | 工业以太网 | Bridge | 需 CC-Link IE Field 网关 |

### 汽车、楼宇与 IoT（9 个）

| 协议 | 类别 | 实现方式 | 说明 |
|------|------|---------|------|
| LIN | 汽车车身总线 | 纯 PHP 串口 | 19200 bps, 主从, PID 校验 |
| K-Line | OBD-II 诊断 | 纯 PHP 串口 | ISO 9141/14230, 5-baud 初始化 |
| FlexRay | 汽车高速总线 | Bridge | 10 Mbps, 需 FlexRay 控制器 |
| LonWorks | 楼宇自动化 | Bridge | 需 Neuron 芯片/接口卡 |
| DALI | 数字照明 | Bridge | 需 DALI 网关 (Lunatone/Helvar) |
| MQTT | IoT 消息 | 纯 PHP TCP | 发布/订阅, Keep-Alive |
| HART-IP | HART over IP | 纯 PHP TCP | 端口 5094 |
| ISA100.11a | 工业无线 | Bridge (802.15.4) | 需 ISA100 网关 |
| WirelessHART | HART 无线 | Bridge | 需 WirelessHART 网关 |

### 需专用硬件 -- Bridge（16 个）

| 协议 | 所需硬件 | 桥接方式 |
|------|---------|---------|
| EtherCAT | ESC 芯片 (Beckhoff TwinCAT / SOEM) | ExternalProcessBridge |
| POWERLINK | openMAC (openPOWERLINK / B&R) | ExternalProcessBridge |
| SERCOS III | FPGA IP 核 (Bosch Rexroth / Hilscher) | TcpGatewayBridge |
| SERCOS I/II | 光纤接口 (早期 SERCOS) | Bridge |
| MOST | 光纤多媒体接口 | Bridge |
| ControlNet | 同轴令牌环接口 (Allen-Bradley) | Bridge |
| INTERBUS | 环网接口 (Phoenix Contact) | Bridge |
| WorldFIP | FIP 总线接口 | Bridge |
| Lightbus | 光纤接口 (Beckhoff) | Bridge |
| SAE J1850 | J1850 PWM/VPW 接口 | Bridge |
| Modbus Plus | 令牌环接口 (Schneider) | Bridge |
| PCI/PCIe | 内核驱动/库桥接 | Bridge |
| VME/VPX | VME 桥接 | Bridge |
| CPCI | CompactPCI 接口 | Bridge |
| Profinet RT/IRT | ERTEC 芯片 (Siemens / Hilscher) | Bridge（规划中） |
| TSN | TSN 网卡 (Intel I225 / NXP SJA1110) | Bridge（规划中） |

---

## 支持的框架

| 框架 | 检测方式 | 协程支持 | 配置机制 | CLI 命令 |
|------|---------|---------|---------|---------|
| **Plain PHP** | 默认回退 | Fiber (PHP 8.1+) | 手动指定 | — |
| **Laravel** | `Illuminate\Foundation\Application` | Octane (Swoole) | ServiceProvider + artisan vendor:publish | `industrial:connect` / `industrial:gateway:list` |
| **Webman** | `Workerman\Worker` | Swoole / Fiber | config/plugin 自动发现 | — |
| **Hyperf** | `Hyperf\Framework\ApplicationFactory` | Swoole 原生 | ConfigProvider + config/autoload | `industrial:connect` / `gateway:list` |
| **ThinkPHP** | `think\App` | think-swoole | services.php 自动发现 | — |
| **Yii2** | `yii\base\Application` | swoole-yii2 | Bootstrap + 组件注册 | — |

检测优先级：`Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Plain PHP`

---

## 快速开始

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-modbus
```

```php
<?php
require 'vendor/autoload.php';

use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

// 1. 创建配置文件
$config = __DIR__ . '/industrial-protocols.php';
file_put_contents($config, '<?php return ' . var_export([
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus', 'variant' => 'tcp',
            'host'     => '192.168.1.10', 'port' => 502,
            'unit_id'  => 1, 'timeout' => 3000,
        ],
    ],
    'gateway' => ['rules' => []],
    'health_check_interval' => 30,
], true) . ';');

// 2. 启动内核
$kernel = new Kernel(['config_path' => $config]);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

// 3. 连接设备并读写
$conn = $kernel->getConnectionManager()->connect('plc-001');

$result = $conn->read('40001');              // 读取保持寄存器
echo "温度: " . $result['40001'] . "\n";

$conn->write(['40001' => 25]);               // 写入保持寄存器

// 4. 健康检查
$health = $kernel->getConnectionManager()->health('plc-001');
echo "状态: {$health->state->value}, 延迟: {$health->latencyMs}ms\n";

$kernel->shutdown();
```

---

## 核心功能

### 内核

| 功能 | 说明 |
|------|------|
| 协议注册 | ProtocolRegistry 自动扫描 Composer 安装的协议包，零配置加载 |
| 连接管理 | 3 种策略（Lazy/Eager/Pooled），支持健康检查、自动重连 |
| 配置管理 | FileConfigRepository / DatabaseConfigRepository(PDO) / EnvConfigRepository |
| 协程适配 | Swoole → Fiber → Sync 三级自动降级，上层组件协程无关 |
| 事件系统 | 13 种事件类型，PSR-14 EventDispatcher，支持自定义监听器 |
| 日志驱动 | PsrLogDriver / FileLogDriver / NullLogDriver |
| 重试策略 | NoRetry / Fixed / ExponentialBackoff / ExponentialBackoff+Jitter |
| 异常体系 | 20+ 分层异常：Connection / Protocol / Device / Gateway |
| 框架适配 | 6 框架自动检测，安装即用 |
| 硬件桥接 | BridgeInterface → ExternalProcessBridge / TcpGatewayBridge → BridgeConnector |
| 厂商适配 | VendorProfile + VendorBridgeFactory，12 家厂商预置配置 |

### 网关引擎

| 功能 | 说明 |
|------|------|
| 规则引擎 | poll（定时轮询）、change（变化触发）、cron（定时表达式） |
| 数据管道 | Source Frame → Parse → Transform → Encode → Target Frame |
| 熔断器 | CLOSED → OPEN → HALF_OPEN 状态机，可配置阈值和冷却时间 |
| 并发执行 | 协程环境下多规则并行执行 |

### 监控与安全

| 功能 | 说明 |
|------|------|
| 指标采集 | Counter / Gauge / Histogram，支持 Prometheus 文本格式导出 |
| 告警通道 | AlertManager + Webhook / Log 通道，多通道同时推送 |
| 输入校验 | InputValidator：设备 ID、地址、端口号、寄存器地址、帧大小、超时校验 |

---

## 协议使用示例

### Modbus TCP

```php
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('plc-001');

$result = $conn->read('40001');                         // 单寄存器读取
$batch  = $conn->read(['40001', '40002']);              // 批量读取
$conn->write(['40001' => 100]);                          // 单寄存器写入
$conn->write(['40001' => 200, '40002' => 300]);          // 批量写入

// 地址格式: 40001-49999 保持寄存器, 30001-39999 输入寄存器, 0-9999 原始偏移
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

### BACnet/IP

```php
use Erikwang2013\IndustrialProtocols\Bacnet\BacnetProtocol;

$kernel->getProtocolRegistry()->register(new BacnetProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('bacnet-device');

$devices = $conn->discoverDevices(5);     // Who-Is 广播发现
$result = $conn->read('0:1:85');          // AnalogInput 1, PresentValue
```

### OPC UA Binary

```php
use Erikwang2013\IndustrialProtocols\OpcUa\OpcUaProtocol;

$kernel->getProtocolRegistry()->register(new OpcUaProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('opcua-server');

$time = $conn->read('i=2258');               // 读取 CurrentTime 节点
$children = $conn->browse('i=85');           // 浏览 Objects 节点
$conn->write(['ns=2;s=SetPoint' => 100.0]);  // 写入节点
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

$conn->write(['sensors/temperature' => '23.5']);   // 发布
$result = $conn->read('sensors/#');                 // 订阅通配符
```

### DNP3

```php
use Erikwang2013\IndustrialProtocols\Dnp3\Dnp3Protocol;

$kernel->getProtocolRegistry()->register(new Dnp3Protocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('rtu-001', [
    'protocol' => 'dnp3', 'host' => '10.0.1.50', 'port' => 20000,
]);

$result = $conn->read('30:1:5');         // Class 0: Group 30, Var 1, Index 5
$conn->write(['10:2:1' => 1]);           // Select-before-operate: Binary Output
```

### HART

```php
use Erikwang2013\IndustrialProtocols\Hart\HartProtocol;

$kernel->getProtocolRegistry()->register(new HartProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('hart-device', [
    'protocol' => 'hart', 'device' => '/dev/ttyUSB1',
]);

$pv = $conn->read('pv');                // 主变量 (Primary Variable)
$current = $conn->read('loop_current'); // 回路电流 (mA)
```

### Bridge (EtherCAT via Vendor Factory)

```php
use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;

// 通过厂商工厂一键创建桥接
$bridge = $kernel->getVendorBridgeFactory()->create('beckhoff', 'CX2030', '3.1');

$conn = new BridgeConnector($bridge, 'ethercat');
$conn->connect();
$result = $conn->read('0x6000:0x01');   // CoE SDO 读取
```

---

## 框架集成示例

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

安装即用。创建 `config/plugin/erikwang2013/industrial-protocols-kernel/config/industrial-protocols.php`，Worker 启动时 ProtocolProcess 自动初始化 Kernel、注册协议包并建立连接。

```php
// config/plugin/erikwang2013/industrial-protocols-kernel/config/industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus', 'variant' => 'tcp',
            'host' => '192.168.1.10', 'port' => 502, 'unit_id' => 1, 'timeout' => 3000,
        ],
    ],
];
```

### Plain PHP（无框架）

```php
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
$kernel->shutdown();
```

---

## 厂商适配

内核内置 12 家主流工业硬件厂商的预置配置文件，无需手动查找 SDK 路径和端口号。

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

**使用示例：**

```php
// 列出所有厂商
$vendors = $kernel->getVendorBridgeFactory()->listVendors();

// 查看厂商设备型号
$devices = $kernel->getVendorBridgeFactory()->getDevices('siemens');
// → [S7-1200 V4.x, S7-1500 V3.x, ET 200SP V2.x, ET 200MP V2.x, S7-400 V6.x]

// 一键创建桥接（SDK 路径自动填充）
$bridge = $kernel->getVendorBridgeFactory()->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
]);
```

配置合并优先级：`厂商默认值 → 设备型号覆盖 → 用户自定义参数`

---

## 配置参考

```php
<?php
// industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol'  => 'modbus',
            'variant'   => 'tcp',
            'host'      => '192.168.1.10',
            'port'      => 502,
            'unit_id'   => 1,
            'timeout'   => 3000,
            'strategy'  => 'lazy',       // lazy | eager | pooled
            'pool_size' => 4,             // pooled 策略时生效
            'points'    => [
                ['address' => '40001', 'name' => 'temperature', 'type' => 'FLOAT32', 'access' => 'RW'],
                ['address' => '40003', 'name' => 'pressure',    'type' => 'FLOAT32', 'access' => 'RO'],
            ],
        ],
    ],
    'gateway' => [
        'rules' => [
            [
                'id'            => 'gw-001',
                'source_device' => 'plc-001',
                'source_point'  => '40001',
                'target_device' => 'opcua-server',
                'target_point'  => 'ns=1;s=Temperature',
                'trigger'       => 'poll',    // poll | change | cron
                'interval'      => 1000,
            ],
        ],
    ],
    'health_check_interval' => 30,
    'default_retry_max'     => 3,
    'default_retry_backoff' => 'exponential',    // exponential | fixed | none
    'default_timeout'       => 3000,
];
```

---

## 文档链接

- [协议 API 参考](docs/protocols.md) -- 42 个协议的连接配置、读写操作、地址格式
- [框架集成指南](docs/framework-integration.md) -- 6 种框架的详细集成说明
- [网关引擎指南](docs/gateway.md) -- 规则定义、触发模式、熔断器配置
- [安全指南](docs/security.md) -- 输入校验、最佳实践、异常参考
- [厂商适配参考](docs/vendors.md) -- 12 家厂商预置配置、设备型号、SDK 路径

---

## 系统要求

- PHP >= 8.1
- Composer 2.x
- 可选：ext-swoole（Swoole 协程加速）
- 可选：ext-pdo（数据库配置存储）
- 可选：串口读写权限（Modbus RTU / HART / LIN / K-Line / CC-Link）
- 可选：C/C++ SDK（EtherCAT / POWERLINK / FlexRay 桥接）
- 可选：网关硬件（PROFIBUS / SERCOS / DALI / IO-Link / 现场总线桥接）

---

## License

MIT
