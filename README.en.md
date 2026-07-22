# Industrial Protocols PHP

A PHP industrial communication protocol suite — micro-kernel + protocol SDK architecture covering 40 protocols, compatible with 6 PHP runtime environments.

> [中文版](README.md)

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [All Protocol Packages](#all-protocol-packages)
- [Supported Frameworks](#supported-frameworks)
- [Quick Start](#quick-start)
- [Configuration Reference](#configuration-reference)
- [Documentation](#documentation)
- [Requirements](#requirements)
- [License](#license)

---

## Overview

Industrial Protocols is an industrial communication protocol suite for the PHP ecosystem, built on a **micro-kernel + protocol SDK** architecture. 40 protocol packages (15 pure PHP + 25 Bridge), each as an independent GitHub repository and Composer package, installed on-demand via Packagist.

**Core philosophy:** The kernel only defines "what a protocol is" — it contains zero protocol implementations. Users install only what they need. The kernel auto-discovers installed protocol packages at boot via the `composer.json` `extra` field.

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-modbus
```

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│                  User Application                │
├─────────────────────────────────────────────────┤
│  Framework Adapters: Laravel · Webman · Hyperf  │
│                     ThinkPHP · Yii2 · Plain PHP  │
├─────────────────────────────────────────────────┤
│                  Micro-Kernel                    │
│  ProtocolRegistry · ConnectionManager(3 strategies)│
│  ConfigRepository · GatewayEngine · CircuitBreaker│
│  CoroutineAdapter · Bridge Layer · Vendor Profiles│
│  Event System(PSR-14) · Log(PSR-3) · Retry(4 types)│
│  Metrics(Prometheus) · Alert · Security · Exception│
├─────────────────────────────────────────────────┤
│           Protocol SDK (6 Core Interfaces)        │
├─────────────────────────────────────────────────┤
│   40 Protocol Packages: 15 Pure PHP + 25 Bridge   │
└─────────────────────────────────────────────────┘
```

---

## All Protocol Packages

### Industrial Ethernet (5)

| Package | Repository | Description |
|---------|-----------|-------------|
| [modbus](https://github.com/erikwang2013/industrial-protocols-modbus) | [erikwang2013/industrial-protocols-modbus](https://packagist.org/packages/erikwang2013/industrial-protocols-modbus) | Modbus TCP/RTU/ASCII, FC 01/03/04/06/10, port 502 |
| [bacnet](https://github.com/erikwang2013/industrial-protocols-bacnet) | [erikwang2013/industrial-protocols-bacnet](https://packagist.org/packages/erikwang2013/industrial-protocols-bacnet) | BACnet/IP, Who-Is/I-Am discovery, ReadProperty, port 47808 |
| [ethernetip](https://github.com/erikwang2013/industrial-protocols-ethernetip) | [erikwang2013/industrial-protocols-ethernetip](https://packagist.org/packages/erikwang2013/industrial-protocols-ethernetip) | EtherNet/IP, ENIP session + CIP Read Tag, port 44818 |
| [opcua](https://github.com/erikwang2013/industrial-protocols-opcua) | [erikwang2013/industrial-protocols-opcua](https://packagist.org/packages/erikwang2013/industrial-protocols-opcua) | OPC UA Binary stack, Session + Read/Write/Browse, port 4840 |
| [profinet](https://github.com/erikwang2013/industrial-protocols-profinet) | [erikwang2013/industrial-protocols-profinet](https://packagist.org/packages/erikwang2013/industrial-protocols-profinet) | Profinet NRT, DCP discovery + Record Data, port 34964 |

### Fieldbus (12)

| Package | Repository | Description |
|---------|-----------|-------------|
| [hart](https://github.com/erikwang2013/industrial-protocols-hart) | [erikwang2013/industrial-protocols-hart](https://packagist.org/packages/erikwang2013/industrial-protocols-hart) | HART 4-20mA FSK, HART modem, PV/loop current |
| [cclink](https://github.com/erikwang2013/industrial-protocols-cclink) | [erikwang2013/industrial-protocols-cclink](https://packagist.org/packages/erikwang2013/industrial-protocols-cclink) | CC-Link RS-485, master-slave polling, CRC-16/XMODEM |
| [dnp3](https://github.com/erikwang2013/industrial-protocols-dnp3) | [erikwang2013/industrial-protocols-dnp3](https://packagist.org/packages/erikwang2013/industrial-protocols-dnp3) | DNP3 power automation, Class 0 poll, port 20000 |
| [iec61850](https://github.com/erikwang2013/industrial-protocols-iec61850) | [erikwang2013/industrial-protocols-iec61850](https://packagist.org/packages/erikwang2013/industrial-protocols-iec61850) | IEC 61850 MMS substation automation, port 102 |
| [profibus](https://github.com/erikwang2013/industrial-protocols-profibus) | [erikwang2013/industrial-protocols-profibus](https://packagist.org/packages/erikwang2013/industrial-protocols-profibus) | PROFIBUS DP/PA/FMS, needs Siemens CP 5611/Anybus |
| [canopen](https://github.com/erikwang2013/industrial-protocols-canopen) | [erikwang2013/industrial-protocols-canopen](https://packagist.org/packages/erikwang2013/industrial-protocols-canopen) | CANopen, needs PCAN-USB/SocketCAN |
| [devicenet](https://github.com/erikwang2013/industrial-protocols-devicenet) | [erikwang2013/industrial-protocols-devicenet](https://packagist.org/packages/erikwang2013/industrial-protocols-devicenet) | DeviceNet, needs Anybus DeviceNet Scanner |
| [foundationfieldbus](https://github.com/erikwang2013/industrial-protocols-foundationfieldbus) | [erikwang2013/industrial-protocols-foundationfieldbus](https://packagist.org/packages/erikwang2013/industrial-protocols-foundationfieldbus) | Foundation Fieldbus H1/HSE, needs NI USB-8486/Softing |
| [asinterface](https://github.com/erikwang2013/industrial-protocols-asinterface) | [erikwang2013/industrial-protocols-asinterface](https://packagist.org/packages/erikwang2013/industrial-protocols-asinterface) | AS-Interface, needs Bihl+Wiedemann/Pepperl+Fuchs |
| [iolink](https://github.com/erikwang2013/industrial-protocols-iolink) | [erikwang2013/industrial-protocols-iolink](https://packagist.org/packages/erikwang2013/industrial-protocols-iolink) | IO-Link, needs ifm/Balluff IO-Link Master |
| [cclinkie](https://github.com/erikwang2013/industrial-protocols-cclinkie) | [erikwang2013/industrial-protocols-cclinkie](https://packagist.org/packages/erikwang2013/industrial-protocols-cclinkie) | CC-Link IE Field Ethernet, needs gateway |

### IoT/Messaging (2)

| Package | Repository | Description |
|---------|-----------|-------------|
| [mqtt](https://github.com/erikwang2013/industrial-protocols-mqtt) | [erikwang2013/industrial-protocols-mqtt](https://packagist.org/packages/erikwang2013/industrial-protocols-mqtt) | MQTT 3.1.1, publish/subscribe + wildcards, port 1883 |
| [hartip](https://github.com/erikwang2013/industrial-protocols-hartip) | [erikwang2013/industrial-protocols-hartip](https://packagist.org/packages/erikwang2013/industrial-protocols-hartip) | HART-IP, HART over TCP/UDP, port 5094 |

### Automotive (5)

| Package | Repository | Description |
|---------|-----------|-------------|
| [lin](https://github.com/erikwang2013/industrial-protocols-lin) | [erikwang2013/industrial-protocols-lin](https://packagist.org/packages/erikwang2013/industrial-protocols-lin) | LIN body bus, 19200 bps UART, master-slave |
| [kline](https://github.com/erikwang2013/industrial-protocols-kline) | [erikwang2013/industrial-protocols-kline](https://packagist.org/packages/erikwang2013/industrial-protocols-kline) | K-Line OBD-II, ISO 9141/14230, 5-baud init |
| [flexray](https://github.com/erikwang2013/industrial-protocols-flexray) | [erikwang2013/industrial-protocols-flexray](https://packagist.org/packages/erikwang2013/industrial-protocols-flexray) | FlexRay high-speed, needs FlexRay controller |
| [saej1850](https://github.com/erikwang2013/industrial-protocols-saej1850) | [erikwang2013/industrial-protocols-saej1850](https://packagist.org/packages/erikwang2013/industrial-protocols-saej1850) | SAE J1850 OBD-II, needs J1850 interface |
| [most](https://github.com/erikwang2013/industrial-protocols-most) | [erikwang2013/industrial-protocols-most](https://packagist.org/packages/erikwang2013/industrial-protocols-most) | MOST fiber multimedia, needs MOST interface |

### Building/Lighting (2)

| Package | Repository | Description |
|---------|-----------|-------------|
| [lonworks](https://github.com/erikwang2013/industrial-protocols-lonworks) | [erikwang2013/industrial-protocols-lonworks](https://packagist.org/packages/erikwang2013/industrial-protocols-lonworks) | LonWorks, needs Neuron chip/interface |
| [dali](https://github.com/erikwang2013/industrial-protocols-dali) | [erikwang2013/industrial-protocols-dali](https://packagist.org/packages/erikwang2013/industrial-protocols-dali) | DALI digital lighting, needs DALI gateway |

### Hardware Bridge (11)

| Package | Repository | Hardware Required |
|---------|-----------|------------------|
| [ethercat](https://github.com/erikwang2013/industrial-protocols-ethercat) | [erikwang2013/industrial-protocols-ethercat](https://packagist.org/packages/erikwang2013/industrial-protocols-ethercat) | Beckhoff TwinCAT 3 / SOEM |
| [powerlink](https://github.com/erikwang2013/industrial-protocols-powerlink) | [erikwang2013/industrial-protocols-powerlink](https://packagist.org/packages/erikwang2013/industrial-protocols-powerlink) | openPOWERLINK / B&R Automation Studio |
| [sercos](https://github.com/erikwang2013/industrial-protocols-sercos) | [erikwang2013/industrial-protocols-sercos](https://packagist.org/packages/erikwang2013/industrial-protocols-sercos) | SERCOS III FPGA IP / Hilscher netX |
| [sercos1](https://github.com/erikwang2013/industrial-protocols-sercos1) | [erikwang2013/industrial-protocols-sercos1](https://packagist.org/packages/erikwang2013/industrial-protocols-sercos1) | SERCOS I/II fiber interface |
| [controlnet](https://github.com/erikwang2013/industrial-protocols-controlnet) | [erikwang2013/industrial-protocols-controlnet](https://packagist.org/packages/erikwang2013/industrial-protocols-controlnet) | Allen-Bradley 1784-PCIC/S |
| [interbus](https://github.com/erikwang2013/industrial-protocols-interbus) | [erikwang2013/industrial-protocols-interbus](https://packagist.org/packages/erikwang2013/industrial-protocols-interbus) | Phoenix Contact IBS |
| [worldfip](https://github.com/erikwang2013/industrial-protocols-worldfip) | [erikwang2013/industrial-protocols-worldfip](https://packagist.org/packages/erikwang2013/industrial-protocols-worldfip) | WorldFIP/Fipio bus interface |
| [lightbus](https://github.com/erikwang2013/industrial-protocols-lightbus) | [erikwang2013/industrial-protocols-lightbus](https://packagist.org/packages/erikwang2013/industrial-protocols-lightbus) | Beckhoff Lightbus fiber interface |
| [modbusplus](https://github.com/erikwang2013/industrial-protocols-modbusplus) | [erikwang2013/industrial-protocols-modbusplus](https://packagist.org/packages/erikwang2013/industrial-protocols-modbusplus) | Schneider Modbus Plus SA85/BM85 |
| [isa100](https://github.com/erikwang2013/industrial-protocols-isa100) | [erikwang2013/industrial-protocols-isa100](https://packagist.org/packages/erikwang2013/industrial-protocols-isa100) | Yokogawa YFGW410 / Honeywell OneWireless |
| [wirelesshart](https://github.com/erikwang2013/industrial-protocols-wirelesshart) | [erikwang2013/industrial-protocols-wirelesshart](https://packagist.org/packages/erikwang2013/industrial-protocols-wirelesshart) | Emerson 1410/1420 Smart Wireless Gateway |

### System Bus (3)

| Package | Repository | Description |
|---------|-----------|-------------|
| [pci](https://github.com/erikwang2013/industrial-protocols-pci) | [erikwang2013/industrial-protocols-pci](https://packagist.org/packages/erikwang2013/industrial-protocols-pci) | PCI/PCIe, needs kernel driver/library bridge |
| [vme](https://github.com/erikwang2013/industrial-protocols-vme) | [erikwang2013/industrial-protocols-vme](https://packagist.org/packages/erikwang2013/industrial-protocols-vme) | VME/VPX backplane, needs VME bridge |
| [cpci](https://github.com/erikwang2013/industrial-protocols-cpci) | [erikwang2013/industrial-protocols-cpci](https://packagist.org/packages/erikwang2013/industrial-protocols-cpci) | CompactPCI, needs CPCI interface |

---

## Supported Frameworks

| Framework | Detection | Coroutine | Integration |
|-----------|-----------|-----------|-------------|
| **Plain PHP** | Default fallback | Fiber | Kernel instantiation |
| **Laravel** | `Illuminate\Foundation\Application` | Octane Swoole | ServiceProvider + Facade + artisan |
| **Webman** | `Workerman\Worker` | Swoole/Fiber | config/plugin auto-discovery |
| **Hyperf** | `Hyperf\Framework\ApplicationFactory` | Swoole native | ConfigProvider + DI |
| **ThinkPHP** | `think\App` | think-swoole | services.php + singleton |
| **Yii2** | `yii\base\Application` | swoole-yii2 | Bootstrap + component |

Detection priority: `Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Plain PHP`

---

## Quick Start

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

## Configuration Reference

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

## Documentation

- [Protocol API Reference](docs/en/protocols.md)
- [Framework Integration Guide](docs/en/framework-integration.md)
- [Gateway Engine Guide](docs/en/gateway.md)
- [Security Guide](docs/en/security.md)
- [Vendor Adapter Reference](docs/en/vendors.md)

## Requirements

- PHP >= 8.1
- Composer

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
