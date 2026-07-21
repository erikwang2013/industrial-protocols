# Industrial Protocols PHP

A PHP industrial communication protocol suite — micro-kernel + protocol SDK architecture covering 42 protocols, compatible with 6 PHP runtime environments.

> [中文版](README.md)

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Supported Protocols](#supported-protocols)
- [Supported Frameworks](#supported-frameworks)
- [Quick Start](#quick-start)
- [Core Features](#core-features)
- [Protocol Examples](#protocol-examples)
- [Framework Integration](#framework-integration)
- [Vendor Adapters](#vendor-adapters)
- [Configuration Reference](#configuration-reference)
- [Documentation](#documentation)
- [Requirements](#requirements)
- [License](#license)

---

## Overview

Industrial Protocols is an industrial communication protocol suite for the PHP ecosystem, built on a **micro-kernel + protocol SDK** architecture. The kernel provides infrastructure — connection management, configuration management, gateway engine, event system, coroutine adaptation — while protocol packages are independent Composer packages installed on-demand, plugging in by implementing unified SDK interfaces.

**Scale:** 42 protocol packages (15 pure PHP + 27 bridge implementations), 351 tests, 731 assertions; 12 pre-configured vendor profiles; 6 framework adapters; PHP >= 8.1.

**Core philosophy:** The kernel only defines "what a protocol is" — it contains zero protocol implementations. Users install only what they need. The kernel auto-discovers protocol packages at boot via Composer's `extra` field. Each protocol package depends solely on the kernel, with zero inter-protocol coupling.

---

## Architecture

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
│  │Profiles  │  Layer   │(PSR-3/File)|(20+ types)│          │  │
│  └──────────┴──────────┴──────────┴──────────┴────────────┘  │
├──────────────────────────────────────────────────────────────┤
│                Protocol SDK (6 Core Interfaces)               │
├──────────────────────────────────────────────────────────────┤
│  42 Protocol Packages: 15 Pure PHP + 27 Bridge               │
└──────────────────────────────────────────────────────────────┘
```

**Key Design Decisions:**

| Decision | Choice |
|----------|--------|
| Architecture | Micro-kernel + Protocol SDK; protocol packages are independently installed, zero coupling |
| Connection Strategies | Lazy (on-demand), Eager (connect at boot), Pooled (connection pool, round-robin) |
| Coroutine Adaptation | Swoole → Fiber → Sync three-tier auto-degradation |
| Retry Strategies | NoRetry / Fixed / ExponentialBackoff / ExponentialBackoff + Jitter |
| Configuration | FileConfig / DatabaseConfig (PDO) / EnvConfig — three implementations |
| Event System | 13 event types based on PSR-14 |
| Gateway Triggers | poll (periodic), change (value-triggered), cron (schedule-based) |
| Hardware Bridge | ExternalProcessBridge (local SDK subprocess) + TcpGatewayBridge (remote gateway TCP) |

---

## Supported Protocols

### Industrial Ethernet (5)

| Protocol | Variant | Implementation | Operations |
|----------|---------|---------------|------------|
| Modbus TCP | TCP | Pure PHP Socket | FC 01/03/04/06/10 |
| BACnet/IP | IP (UDP) | Pure PHP UDP | Who-Is/I-Am, ReadProperty |
| EtherNet/IP | TCP | Pure PHP Socket | ENIP session, CIP Read Tag |
| OPC UA | UA Binary/TCP | Pure PHP UA Binary Stack | CreateSession, Read, Write, Browse |
| Profinet NRT | NRT (UDP/TCP) | Pure PHP Socket | DCP discovery, Record Data read/write |

### Fieldbus (12)

| Protocol | Variant | Implementation | Notes |
|----------|---------|---------------|-------|
| Modbus RTU/ASCII | RS-485 Serial | Pure PHP Serial | CRC16 checksum |
| HART | 4-20mA FSK | Pure PHP Serial | HART modem, PV/loop current |
| CC-Link RS-485 | RS-485 | Pure PHP Serial | Master-slave polling, CRC-16/XMODEM |
| DNP3 | TCP/Serial | Pure PHP | Power automation, Class 0 poll |
| IEC 61850 | MMS over TCP | Pure PHP | Substation automation, IED data paths |
| PROFIBUS DP/PA/FMS | RS-485/MBP | Bridge (Anybus/Siemens CP) | Gateway or interface card required |
| CANopen | CAN | Bridge (PCAN/SocketCAN) | CAN interface required |
| DeviceNet | CAN | Bridge (Anybus) | DeviceNet Scanner required |
| Foundation Fieldbus | H1/HSE | Bridge (NI/Softing) | FF interface required |
| AS-Interface | AS-i | Bridge (Bihl+Wiedemann/P+F) | AS-i gateway required |
| IO-Link | Point-to-Point | Bridge (ifm/Balluff) | IO-Link Master required |
| CC-Link IE | Industrial Ethernet | Bridge | CC-Link IE Field gateway required |

### Automotive, Building & IoT (9)

| Protocol | Category | Implementation | Notes |
|----------|----------|---------------|-------|
| LIN | Automotive body bus | Pure PHP Serial | 19200 bps, master-slave, PID parity |
| K-Line | OBD-II diagnostics | Pure PHP Serial | ISO 9141/14230, 5-baud init |
| FlexRay | Automotive high-speed | Bridge | 10 Mbps, FlexRay controller required |
| LonWorks | Building automation | Bridge | Neuron chip / interface card required |
| DALI | Digital lighting | Bridge | DALI gateway (Lunatone/Helvar) required |
| MQTT | IoT messaging | Pure PHP TCP | Publish/Subscribe, Keep-Alive |
| HART-IP | HART over IP | Pure PHP TCP | Port 5094 |
| ISA100.11a | Industrial wireless | Bridge (802.15.4) | ISA100 gateway required |
| WirelessHART | HART wireless | Bridge | WirelessHART gateway required |

### Hardware Bridge (16)

| Protocol | Hardware Required | Bridge Method |
|----------|------------------|---------------|
| EtherCAT | ESC chip (Beckhoff TwinCAT / SOEM) | ExternalProcessBridge |
| POWERLINK | openMAC (openPOWERLINK / B&R) | ExternalProcessBridge |
| SERCOS III | FPGA IP core (Bosch Rexroth / Hilscher) | TcpGatewayBridge |
| SERCOS I/II | Fiber optic interface (legacy SERCOS) | Bridge |
| MOST | Fiber optic multimedia interface | Bridge |
| ControlNet | Coax token-ring interface (Allen-Bradley) | Bridge |
| INTERBUS | Ring network interface (Phoenix Contact) | Bridge |
| WorldFIP | FIP bus interface | Bridge |
| Lightbus | Fiber optic interface (Beckhoff) | Bridge |
| SAE J1850 | J1850 PWM/VPW interface | Bridge |
| Modbus Plus | Token-ring interface (Schneider) | Bridge |
| PCI/PCIe | Kernel driver/library bridge | Bridge |
| VME/VPX | VME bridge | Bridge |
| CPCI | CompactPCI interface | Bridge |
| Profinet RT/IRT | ERTEC chip (Siemens / Hilscher) | Bridge (planned) |
| TSN | TSN NIC (Intel I225 / NXP SJA1110) | Bridge (planned) |

---

## Supported Frameworks

| Framework | Detection | Coroutine | Config Mechanism | CLI Commands |
|-----------|-----------|-----------|------------------|-------------|
| **Plain PHP** | Default fallback | Fiber (PHP 8.1+) | Manual config path | — |
| **Laravel** | `Illuminate\Foundation\Application` | Octane (Swoole) | ServiceProvider + artisan vendor:publish | `industrial:connect` / `industrial:gateway:list` |
| **Webman** | `Workerman\Worker` | Swoole / Fiber | config/plugin auto-discovery | — |
| **Hyperf** | `Hyperf\Framework\ApplicationFactory` | Swoole native | ConfigProvider + config/autoload | `industrial:connect` / `gateway:list` |
| **ThinkPHP** | `think\App` | think-swoole | services.php auto-discovery | — |
| **Yii2** | `yii\base\Application` | swoole-yii2 | Bootstrap + component registration | — |

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
            'host'     => '192.168.1.10', 'port' => 502,
            'unit_id'  => 1, 'timeout' => 3000,
        ],
    ],
    'gateway' => ['rules' => []],
    'health_check_interval' => 30,
], true) . ';');

// 2. Boot kernel
$kernel = new Kernel(['config_path' => $config]);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

// 3. Connect and read/write
$conn = $kernel->getConnectionManager()->connect('plc-001');

$result = $conn->read('40001');              // Read holding register
echo "Temperature: " . $result['40001'] . "\n";

$conn->write(['40001' => 25]);               // Write holding register

// 4. Health check
$health = $kernel->getConnectionManager()->health('plc-001');
echo "State: {$health->state->value}, Latency: {$health->latencyMs}ms\n";

$kernel->shutdown();
```

---

## Core Features

### Kernel

| Feature | Description |
|---------|-------------|
| Protocol Registry | Auto-scans Composer-installed protocol packages, zero-config loading |
| Connection Manager | 3 strategies (Lazy/Eager/Pooled) with health checks and auto-reconnection |
| Config Management | FileConfigRepository / DatabaseConfigRepository(PDO) / EnvConfigRepository |
| Coroutine Adaptation | Swoole → Fiber → Sync three-tier auto-degradation |
| Event System | 13 event types, PSR-14 EventDispatcher, custom listener support |
| Log Drivers | PsrLogDriver / FileLogDriver / NullLogDriver |
| Retry Strategies | NoRetry / Fixed / ExponentialBackoff / ExponentialBackoff + Jitter |
| Exception Hierarchy | 20+ layered exceptions: Connection / Protocol / Device / Gateway |
| Framework Adapters | 6 frameworks + plain PHP, auto-detected at boot |
| Hardware Bridge | BridgeInterface → ExternalProcessBridge / TcpGatewayBridge → BridgeConnector |
| Vendor Adapters | VendorProfile + VendorBridgeFactory, 12 pre-configured vendor profiles |

### Gateway Engine

| Feature | Description |
|---------|-------------|
| Rule Engine | poll (periodic), change (value-triggered), cron (schedule-based) trigger modes |
| Data Pipeline | Source Frame → Parse → Transform → Encode → Target Frame |
| Circuit Breaker | CLOSED → OPEN → HALF_OPEN state machine with configurable thresholds |
| Concurrent Execution | Rules execute in parallel in coroutine environments |

### Monitoring & Security

| Feature | Description |
|---------|-------------|
| Metrics | Counter / Gauge / Histogram with Prometheus text format export |
| Alert Channels | AlertManager + Webhook / Log channels, multi-channel push |
| Input Validation | Device ID, host, port, register address, frame size, timeout validation |

---

## Protocol Examples

### Modbus TCP

```php
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('plc-001');

$result = $conn->read('40001');                         // Single register read
$batch  = $conn->read(['40001', '40002']);              // Batch read
$conn->write(['40001' => 100]);                          // Single register write
$conn->write(['40001' => 200, '40002' => 300]);          // Batch write

// Address: 40001-49999 Holding Register, 30001-39999 Input Register, 0-9999 raw offset
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

### BACnet/IP

```php
use Erikwang2013\IndustrialProtocols\Bacnet\BacnetProtocol;

$kernel->getProtocolRegistry()->register(new BacnetProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('bacnet-device');

$devices = $conn->discoverDevices(5);     // Who-Is broadcast discovery
$result = $conn->read('0:1:85');          // AnalogInput 1, PresentValue
```

### OPC UA Binary

```php
use Erikwang2013\IndustrialProtocols\OpcUa\OpcUaProtocol;

$kernel->getProtocolRegistry()->register(new OpcUaProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('opcua-server');

$time = $conn->read('i=2258');               // Read CurrentTime node
$children = $conn->browse('i=85');           // Browse Objects node
$conn->write(['ns=2;s=SetPoint' => 100.0]);  // Write node
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

$conn->write(['sensors/temperature' => '23.5']);   // publish
$result = $conn->read('sensors/#');                 // subscribe wildcard
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

$pv = $conn->read('pv');                // Primary Variable
$current = $conn->read('loop_current'); // Loop current (mA)
```

### Bridge (EtherCAT via Vendor Factory)

```php
use Erikwang2013\IndustrialProtocols\Bridge\BridgeConnector;

// One-click bridge creation via vendor factory
$bridge = $kernel->getVendorBridgeFactory()->create('beckhoff', 'CX2030', '3.1');

$conn = new BridgeConnector($bridge, 'ethercat');
$conn->connect();
$result = $conn->read('0x6000:0x01');   // CoE SDO read
```

---

## Framework Integration

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

Works out of the box after installation. Create `config/plugin/industrial-protocols/kernel/config/industrial-protocols.php`. ProtocolProcess auto-initializes the Kernel, registers protocol packages, and establishes connections on worker start — no extra code required.

```php
// config/plugin/industrial-protocols/kernel/config/industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus', 'variant' => 'tcp',
            'host' => '192.168.1.10', 'port' => 502, 'unit_id' => 1, 'timeout' => 3000,
        ],
    ],
];
```

### Plain PHP (no framework)

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

## Vendor Adapters

The kernel includes pre-configured profiles for 12 major industrial hardware vendors, eliminating the need to manually look up SDK paths and port numbers.

| Vendor | Protocol | Bridge Type | Device Count |
|--------|----------|------------|-------------|
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

**Usage:**

```php
// List all vendors
$vendors = $kernel->getVendorBridgeFactory()->listVendors();

// View a vendor's device models
$devices = $kernel->getVendorBridgeFactory()->getDevices('siemens');
// → [S7-1200 V4.x, S7-1500 V3.x, ET 200SP V2.x, ET 200MP V2.x, S7-400 V6.x]

// One-click bridge creation (SDK path auto-filled)
$bridge = $kernel->getVendorBridgeFactory()->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
]);
```

Configuration merge priority: `Vendor defaults → Device model overrides → User custom parameters`

---

## Configuration Reference

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
            'pool_size' => 4,             // Effective with pooled strategy
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

## Documentation

- [Protocol API Reference](docs/en/protocols.md) — Connection config, read/write ops, address formats for 42 protocols
- [Framework Integration Guide](docs/en/framework-integration.md) — Detailed integration for 6 frameworks
- [Gateway Engine Guide](docs/en/gateway.md) — Rules, trigger modes, circuit breaker configuration
- [Security Guide](docs/en/security.md) — Input validation, best practices, exception reference
- [Vendor Adapters Reference](docs/en/vendors.md) — Pre-configured profiles, device models, SDK paths for 12 vendors

---

## Requirements

- PHP >= 8.1
- Composer 2.x
- Optional: ext-swoole (Swoole coroutine acceleration)
- Optional: ext-pdo (database config storage)
- Optional: serial port permissions (Modbus RTU / HART / LIN / K-Line / CC-Link)
- Optional: C/C++ SDK (EtherCAT / POWERLINK / FlexRay bridge)
- Optional: gateway hardware (PROFIBUS / SERCOS / DALI / IO-Link / fieldbus bridging)

---

## License

MIT
