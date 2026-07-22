# Industrial Protocols PHP

PHP 工业网络通信协议集 —— 微内核 + 协议 SDK 架构，覆盖 40 种工业协议，兼容 6 种 PHP 运行时环境。

> [English](README.en.md)

## 目录

- [项目简介](#项目简介)
- [架构概览](#架构概览)
- [所有协议包](#所有协议包)
- [支持的框架](#支持的框架)
- [快速开始](#快速开始)
- [配置参考](#配置参考)
- [文档](#文档)
- [系统要求](#系统要求)
- [License](#license)

---

## 项目简介

Industrial Protocols 是一个面向 PHP 生态的工业通信协议集，采用 **微内核 + 协议 SDK** 架构。40 个协议包（15 个纯 PHP 实现 + 25 个 Bridge 桥接），每个包都是独立 GitHub 仓库和 Composer 包，通过 Packagist 按需安装。

**核心理念：** 内核只定义「协议是什么」，不包含任何具体协议实现。用户按需安装协议包，内核启动时通过 `composer.json` 的 `extra` 字段自动发现并注册。

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-modbus
```

---

## 架构概览

```
┌─────────────────────────────────────────────────┐
│                  User Application                │
├─────────────────────────────────────────────────┤
│  Framework Adapters: Laravel · Webman · Hyperf  │
│                     ThinkPHP · Yii2 · Plain PHP  │
├─────────────────────────────────────────────────┤
│                  Micro-Kernel                    │
│  ProtocolRegistry · ConnectionManager(3策略)      │
│  ConfigRepository · GatewayEngine · CircuitBreaker│
│  CoroutineAdapter · Bridge Layer · Vendor Profiles│
│  Event System(PSR-14) · Log(PSR-3) · Retry(4种)  │
│  Metrics(Prometheus) · Alert · Security · Exception│
├─────────────────────────────────────────────────┤
│           Protocol SDK (6 Core Interfaces)        │
├─────────────────────────────────────────────────┤
│   40 Protocol Packages: 15 Pure PHP + 25 Bridge   │
└─────────────────────────────────────────────────┘
```

---

## 所有协议包

### 工业以太网（5）

| 包 | 仓库 | 说明 |
|----|------|------|
| [modbus](https://github.com/erikwang2013/industrial-protocols-modbus) | [erikwang2013/industrial-protocols-modbus](https://packagist.org/packages/erikwang2013/industrial-protocols-modbus) | Modbus TCP/RTU/ASCII，FC 01/03/04/06/10，端口 502 |
| [bacnet](https://github.com/erikwang2013/industrial-protocols-bacnet) | [erikwang2013/industrial-protocols-bacnet](https://packagist.org/packages/erikwang2013/industrial-protocols-bacnet) | BACnet/IP，Who-Is/I-Am 设备发现，ReadProperty，端口 47808 |
| [ethernetip](https://github.com/erikwang2013/industrial-protocols-ethernetip) | [erikwang2013/industrial-protocols-ethernetip](https://packagist.org/packages/erikwang2013/industrial-protocols-ethernetip) | EtherNet/IP，ENIP 会话管理 + CIP Read Tag，端口 44818 |
| [opcua](https://github.com/erikwang2013/industrial-protocols-opcua) | [erikwang2013/industrial-protocols-opcua](https://packagist.org/packages/erikwang2013/industrial-protocols-opcua) | OPC UA Binary 协议栈，Session + Read/Write/Browse，端口 4840 |
| [profinet](https://github.com/erikwang2013/industrial-protocols-profinet) | [erikwang2013/industrial-protocols-profinet](https://packagist.org/packages/erikwang2013/industrial-protocols-profinet) | Profinet NRT，DCP 设备发现 + Record Data 读写，端口 34964 |

### 现场总线（12）

| 包 | 仓库 | 说明 |
|----|------|------|
| [hart](https://github.com/erikwang2013/industrial-protocols-hart) | [erikwang2013/industrial-protocols-hart](https://packagist.org/packages/erikwang2013/industrial-protocols-hart) | HART 4-20mA FSK，HART 调制解调器，PV/回路电流 |
| [cclink](https://github.com/erikwang2013/industrial-protocols-cclink) | [erikwang2013/industrial-protocols-cclink](https://packagist.org/packages/erikwang2013/industrial-protocols-cclink) | CC-Link RS-485，主从轮询，CRC-16/XMODEM |
| [dnp3](https://github.com/erikwang2013/industrial-protocols-dnp3) | [erikwang2013/industrial-protocols-dnp3](https://packagist.org/packages/erikwang2013/industrial-protocols-dnp3) | DNP3 电力自动化，Class 0 轮询，端口 20000 |
| [iec61850](https://github.com/erikwang2013/industrial-protocols-iec61850) | [erikwang2013/industrial-protocols-iec61850](https://packagist.org/packages/erikwang2013/industrial-protocols-iec61850) | IEC 61850 MMS 变电站自动化，端口 102 |
| [profibus](https://github.com/erikwang2013/industrial-protocols-profibus) | [erikwang2013/industrial-protocols-profibus](https://packagist.org/packages/erikwang2013/industrial-protocols-profibus) | PROFIBUS DP/PA/FMS，需 Siemens CP 5611/Anybus 网关 |
| [canopen](https://github.com/erikwang2013/industrial-protocols-canopen) | [erikwang2013/industrial-protocols-canopen](https://packagist.org/packages/erikwang2013/industrial-protocols-canopen) | CANopen，需 PCAN-USB/SocketCAN 接口 |
| [devicenet](https://github.com/erikwang2013/industrial-protocols-devicenet) | [erikwang2013/industrial-protocols-devicenet](https://packagist.org/packages/erikwang2013/industrial-protocols-devicenet) | DeviceNet，需 Anybus DeviceNet Scanner |
| [foundationfieldbus](https://github.com/erikwang2013/industrial-protocols-foundationfieldbus) | [erikwang2013/industrial-protocols-foundationfieldbus](https://packagist.org/packages/erikwang2013/industrial-protocols-foundationfieldbus) | Foundation Fieldbus H1/HSE，需 NI USB-8486/Softing FFusb |
| [asinterface](https://github.com/erikwang2013/industrial-protocols-asinterface) | [erikwang2013/industrial-protocols-asinterface](https://packagist.org/packages/erikwang2013/industrial-protocols-asinterface) | AS-Interface，需 Bihl+Wiedemann/Pepperl+Fuchs 网关 |
| [iolink](https://github.com/erikwang2013/industrial-protocols-iolink) | [erikwang2013/industrial-protocols-iolink](https://packagist.org/packages/erikwang2013/industrial-protocols-iolink) | IO-Link，需 ifm/Balluff IO-Link Master |
| [cclinkie](https://github.com/erikwang2013/industrial-protocols-cclinkie) | [erikwang2013/industrial-protocols-cclinkie](https://packagist.org/packages/erikwang2013/industrial-protocols-cclinkie) | CC-Link IE Field 工业以太网版，需网关 |

### IoT/消息（2）

| 包 | 仓库 | 说明 |
|----|------|------|
| [mqtt](https://github.com/erikwang2013/industrial-protocols-mqtt) | [erikwang2013/industrial-protocols-mqtt](https://packagist.org/packages/erikwang2013/industrial-protocols-mqtt) | MQTT 3.1.1，发布/订阅 + 通配符，端口 1883 |
| [hartip](https://github.com/erikwang2013/industrial-protocols-hartip) | [erikwang2013/industrial-protocols-hartip](https://packagist.org/packages/erikwang2013/industrial-protocols-hartip) | HART-IP，HART over TCP/UDP，端口 5094 |

### 汽车总线（5）

| 包 | 仓库 | 说明 |
|----|------|------|
| [lin](https://github.com/erikwang2013/industrial-protocols-lin) | [erikwang2013/industrial-protocols-lin](https://packagist.org/packages/erikwang2013/industrial-protocols-lin) | LIN 车身总线，19200 bps UART，主从模式 |
| [kline](https://github.com/erikwang2013/industrial-protocols-kline) | [erikwang2013/industrial-protocols-kline](https://packagist.org/packages/erikwang2013/industrial-protocols-kline) | K-Line OBD-II，ISO 9141/14230，5-baud 初始化 |
| [flexray](https://github.com/erikwang2013/industrial-protocols-flexray) | [erikwang2013/industrial-protocols-flexray](https://packagist.org/packages/erikwang2013/industrial-protocols-flexray) | FlexRay 汽车高速总线，需 FlexRay 控制器 |
| [saej1850](https://github.com/erikwang2013/industrial-protocols-saej1850) | [erikwang2013/industrial-protocols-saej1850](https://packagist.org/packages/erikwang2013/industrial-protocols-saej1850) | SAE J1850 OBD-II 早期标准，需 J1850 接口 |
| [most](https://github.com/erikwang2013/industrial-protocols-most) | [erikwang2013/industrial-protocols-most](https://packagist.org/packages/erikwang2013/industrial-protocols-most) | MOST 光纤多媒体，需 MOST 接口 |

### 楼宇/照明（2）

| 包 | 仓库 | 说明 |
|----|------|------|
| [lonworks](https://github.com/erikwang2013/industrial-protocols-lonworks) | [erikwang2013/industrial-protocols-lonworks](https://packagist.org/packages/erikwang2013/industrial-protocols-lonworks) | LonWorks 楼宇自动化，需 Neuron 芯片/接口卡 |
| [dali](https://github.com/erikwang2013/industrial-protocols-dali) | [erikwang2013/industrial-protocols-dali](https://packagist.org/packages/erikwang2013/industrial-protocols-dali) | DALI 数字照明，需 DALI 网关 |

### 硬件桥接（11）

| 包 | 仓库 | 所需硬件 |
|----|------|---------|
| [ethercat](https://github.com/erikwang2013/industrial-protocols-ethercat) | [erikwang2013/industrial-protocols-ethercat](https://packagist.org/packages/erikwang2013/industrial-protocols-ethercat) | Beckhoff TwinCAT 3 / SOEM |
| [powerlink](https://github.com/erikwang2013/industrial-protocols-powerlink) | [erikwang2013/industrial-protocols-powerlink](https://packagist.org/packages/erikwang2013/industrial-protocols-powerlink) | openPOWERLINK / B&R Automation Studio |
| [sercos](https://github.com/erikwang2013/industrial-protocols-sercos) | [erikwang2013/industrial-protocols-sercos](https://packagist.org/packages/erikwang2013/industrial-protocols-sercos) | SERCOS III FPGA IP / Hilscher netX |
| [sercos1](https://github.com/erikwang2013/industrial-protocols-sercos1) | [erikwang2013/industrial-protocols-sercos1](https://packagist.org/packages/erikwang2013/industrial-protocols-sercos1) | SERCOS I/II 光纤接口 |
| [controlnet](https://github.com/erikwang2013/industrial-protocols-controlnet) | [erikwang2013/industrial-protocols-controlnet](https://packagist.org/packages/erikwang2013/industrial-protocols-controlnet) | Allen-Bradley 1784-PCIC/S |
| [interbus](https://github.com/erikwang2013/industrial-protocols-interbus) | [erikwang2013/industrial-protocols-interbus](https://packagist.org/packages/erikwang2013/industrial-protocols-interbus) | Phoenix Contact IBS |
| [worldfip](https://github.com/erikwang2013/industrial-protocols-worldfip) | [erikwang2013/industrial-protocols-worldfip](https://packagist.org/packages/erikwang2013/industrial-protocols-worldfip) | WorldFIP/Fipio 总线接口 |
| [lightbus](https://github.com/erikwang2013/industrial-protocols-lightbus) | [erikwang2013/industrial-protocols-lightbus](https://packagist.org/packages/erikwang2013/industrial-protocols-lightbus) | Beckhoff Lightbus 光纤接口 |
| [modbusplus](https://github.com/erikwang2013/industrial-protocols-modbusplus) | [erikwang2013/industrial-protocols-modbusplus](https://packagist.org/packages/erikwang2013/industrial-protocols-modbusplus) | Schneider Modbus Plus SA85/BM85 |
| [isa100](https://github.com/erikwang2013/industrial-protocols-isa100) | [erikwang2013/industrial-protocols-isa100](https://packagist.org/packages/erikwang2013/industrial-protocols-isa100) | Yokogawa YFGW410 / Honeywell OneWireless |
| [wirelesshart](https://github.com/erikwang2013/industrial-protocols-wirelesshart) | [erikwang2013/industrial-protocols-wirelesshart](https://packagist.org/packages/erikwang2013/industrial-protocols-wirelesshart) | Emerson 1410/1420 Smart Wireless Gateway |

### 系统总线（3）

| 包 | 仓库 | 说明 |
|----|------|------|
| [pci](https://github.com/erikwang2013/industrial-protocols-pci) | [erikwang2013/industrial-protocols-pci](https://packagist.org/packages/erikwang2013/industrial-protocols-pci) | PCI/PCIe 系统总线，需内核驱动/库桥接 |
| [vme](https://github.com/erikwang2013/industrial-protocols-vme) | [erikwang2013/industrial-protocols-vme](https://packagist.org/packages/erikwang2013/industrial-protocols-vme) | VME/VPX 工控背板，需 VME 桥接模块 |
| [cpci](https://github.com/erikwang2013/industrial-protocols-cpci) | [erikwang2013/industrial-protocols-cpci](https://packagist.org/packages/erikwang2013/industrial-protocols-cpci) | CompactPCI 机架式 PCI，需 CPCI 接口 |

---

## 支持的框架

| 框架 | 检测方式 | 协程 | 集成方式 |
|------|---------|------|---------|
| **Plain PHP** | 默认回退 | Fiber | Kernel 实例化 |
| **Laravel** | `Illuminate\Foundation\Application` | Octane Swoole | ServiceProvider + Facade + artisan |
| **Webman** | `Workerman\Worker` | Swoole/Fiber | config/plugin 自动发现 |
| **Hyperf** | `Hyperf\Framework\ApplicationFactory` | Swoole 原生 | ConfigProvider + DI |
| **ThinkPHP** | `think\App` | think-swoole | services.php + 单例 |
| **Yii2** | `yii\base\Application` | swoole-yii2 | Bootstrap + 组件 |

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

$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
echo $result['40001'];

$conn->write(['40001' => 25]);

$health = $kernel->getConnectionManager()->health('plc-001');
echo "{$health->state->value}, {$health->latencyMs}ms";

$kernel->shutdown();
```

---

## 配置参考

```php
<?php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus', 'variant' => 'tcp',
            'host' => '192.168.1.10', 'port' => 502,
            'unit_id' => 1, 'timeout' => 3000,
        ],
    ],
    'gateway' => ['rules' => []],
    'health_check_interval' => 30,
    'default_retry_max' => 3,
    'default_retry_backoff' => 'exponential',
];
```

---

## 文档

- [协议 API 参考](docs/protocols.md)
- [框架集成指南](docs/framework-integration.md)
- [网关引擎指南](docs/gateway.md)
- [安全指南](docs/security.md)
- [厂商适配参考](docs/vendors.md)

## 系统要求

- PHP >= 8.1
- Composer

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
