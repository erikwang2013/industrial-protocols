# 协议 API 参考

> [English](en/protocols.md)

## Modbus TCP/RTU

### 连接配置

```php
'devices' => [
    'plc-001' => [
        'protocol' => 'modbus',
        'variant'  => 'tcp',        // tcp | rtu | ascii
        'host'     => '192.168.1.10',
        'port'     => 502,
        'unit_id'  => 1,
        'timeout'  => 3000,          // 毫秒
    ],
]
```

### 读取寄存器

```php
$conn = $manager->connect('plc-001');

// 单个寄存器
$result = $conn->read('40001');         // ['40001' => 42]

// 批量读取
$result = $conn->read(['40001', '40002']);  // ['40001' => 42, '40002' => 100]
```

### 写入寄存器

```php
$conn->write('40001', [100]);                         // 单寄存器写入
$conn->write(['40001', '40002'], [200, 300]);          // 多寄存器按索引
$conn->write(['40001', '40002'], ['40001' => 200, '40002' => 300]);  // 多寄存器按键名
```

### 地址格式

| 范围 | 类型 | 偏移计算 |
|-------|------|--------|
| 40001-49999 | 保持寄存器（读写） | 地址 - 40001 |
| 30001-39999 | 输入寄存器（只读） | 地址 - 30001 |
| 0-9999 | 原始偏移 | 直接使用 |

### 健康检查

```php
$health = $manager->health('plc-001');
echo $health->state->value;    // HEALTHY（健康）| CLOSED（已关闭）| FAILED（故障）
echo $health->latencyMs;       // 往返延迟（毫秒）
echo $health->lastError;       // 最后错误信息
```

## BACnet/IP

### 连接配置

```php
'devices' => [
    'bacnet-device' => [
        'protocol'  => 'bacnet',
        'variant'   => 'ip',
        'host'      => '192.168.1.50',
        'port'      => 47808,
        'device_id' => 1234,
        'timeout'   => 3000,
    ],
]
```

### 设备发现

```php
$conn = $manager->connect('bacnet-device');
$devices = $conn->discoverDevices(5); // 5 秒超时，Who-Is 广播
```

### 读取属性

```php
// 格式：ObjectType:ObjectInstance:PropertyId
$result = $conn->read('0:1:85');  // AnalogInput 1，PresentValue（当前值）
$result = $conn->read('0:2:85');  // AnalogInput 2，PresentValue
```

如果省略 PropertyId，默认使用 PresentValue (85)。

## EtherNet/IP

### 连接配置

```php
'devices' => [
    'eip-plc' => [
        'protocol' => 'ethernet-ip',
        'variant'  => 'tcp',
        'host'     => '192.168.1.20',
        'port'     => 44818,
        'timeout'  => 3000,
    ],
]
```

### 读取 CIP 标签

```php
$conn = $manager->connect('eip-plc');
$result = $conn->read('MyTag');          // ['MyTag' => <值>]
$result = $conn->read(['Tag1', 'Tag2']); // ['Tag1' => <v1>, 'Tag2' => <v2>]
```

EtherNet/IP 连接器在连接时自动注册 CIP 会话，断开时自动注销。

## OPC UA Binary

### 连接配置

```php
'devices' => [
    'opcua-server' => [
        'protocol'        => 'opc-ua',
        'variant'         => 'binary',
        'host'            => '192.168.1.100',
        'port'            => 4840,
        'timeout'         => 5000,
        'application_uri' => 'urn:myapp:industrial-protocols',
        'session_name'    => 'PHP-OPCUA-Client',
    ],
]
```

### 读取节点值

```php
$conn = $manager->connect('opcua-server');

// 读取节点（支持多种地址格式）
$result = $conn->read('ns=0;i=2258');        // CurrentTime
$result = $conn->read('i=2258');              // 默认 ns=0
$result = $conn->read('ns=2;s=Temperature');  // 字符串标识符

// 批量读取
$result = $conn->read(['ns=0;i=2258', 'ns=2;s=Temperature']);
```

### 写入节点值

```php
$conn->write(['ns=2;s=SetPoint' => 100.0]);
```

### 浏览地址空间

```php
$children = $conn->browse('i=85'); // 浏览 Objects 文件夹下的节点
```

### 地址格式

| 格式 | 示例 | 说明 |
|------|------|------|
| `ns=N;i=N` | `ns=0;i=2258` | 带命名空间的数字标识符 |
| `i=N` | `i=2258` | 不带命名空间的数字标识符（ns=0）|
| `ns=N;s=X` | `ns=2;s=Temperature` | 字符串标识符 |
| `s=X` | `s=MyVar` | 不带命名空间的字符串标识符 |

## Profinet NRT

> **注意：** Profinet 分为 RT（实时）和 NRT（非实时）两个通道。RT 通道需要 ERTEC 专用硬件芯片，PHP 无法实现。本包实现 NRT 通道：DCP 设备发现、Record Data 读写、诊断。

### 连接配置

```php
'devices' => [
    'pn-device' => [
        'protocol'  => 'profinet',
        'variant'   => 'nrt',
        'host'      => '192.168.1.30',
        'port'      => 34964,
        'transport' => 'udp',     // UDP（DCP）或 TCP（Record Data）
        'timeout'   => 5000,
    ],
]
```

### 设备发现（DCP）

```php
$conn = $manager->connect('pn-device');
$devices = $conn->discoverDevices(5); // DCP Identify 广播
// 返回: [['name' => 'pn-device-1', 'ip' => '192.168.1.30'], ...]
```

### 读取 Record Data

```php
// 地址格式：api:slot:subslot:index
$result = $conn->read('0:0:1:0xAFF0');  // 读取模块诊断数据
$result = $conn->read('0:1:1:0x0001');  // 读取模块参数
```

### 写入 Record Data

```php
$conn->write(['0:0:1:0x0100' => 0x0001]); // 写入参数
```

## 硬件桥接协议（EtherCAT / POWERLINK / SERCOS III / Profinet RT / TSN）

以下协议需要专用硬件芯片或实时内核，PHP 无法直接实现协议栈。本库通过 **Bridge 层** 适配厂商 C/C++ SDK 或网关硬件。

### 桥接方式

| 桥接器 | 说明 | 适用场景 |
|--------|------|---------|
| `ExternalProcessBridge` | 启动 C/C++ SDK 子进程，通过 stdin/stdout 通信 | 厂商提供命令行 SDK 工具 |
| `TcpGatewayBridge` | TCP/UDP 连接网关硬件 | Anybus / Hilscher / 自研网关 |

### 桥接配置示例

```php
use IndustrialProtocols\Bridge\ExternalProcessBridge;
use IndustrialProtocols\Bridge\TcpGatewayBridge;
use IndustrialProtocols\EtherCat\EtherCatProtocol;

// 方式 1：通过 C/C++ SDK 子进程
$bridge = new ExternalProcessBridge('/opt/ethercat-sdk/bin/ecat_master');
$kernel->getProtocolRegistry()->register(new EtherCatProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('ethercat-device', [
    'protocol' => 'ethercat',
    'bridge'   => $bridge,
]);
$result = $conn->read('0x6000:0x01'); // CoE SDO 读取

// 方式 2：通过网关硬件
$bridge = new TcpGatewayBridge('192.168.1.200', 5555);
$conn = $kernel->getConnectionManager()->connect('powerlink-device', [
    'protocol' => 'powerlink',
    'bridge'   => $bridge,
]);
```

### 支持的桥接协议

| 协议 | 所需硬件/SDK | Bridge 类型 |
|------|------------|------------|
| EtherCAT | Beckhoff TwinCAT SDK / SOEM (Simple Open EtherCAT Master) | ExternalProcessBridge |
| POWERLINK | openPOWERLINK stack / B&R Automation Studio | ExternalProcessBridge |
| SERCOS III | Bosch Rexroth SERCOS IP 核 / Hilscher netX | TcpGatewayBridge |
| Profinet RT/IRT | Siemens ERTEC / Hilscher netX | TcpGatewayBridge |
| TSN | TSN 网卡 (Intel I225-T1 / NXP SJA1110) + 802.1Qbv 驱动 | ExternalProcessBridge |

## 连接管理

### ConnectionManager API

```php
$manager = $kernel->getConnectionManager();

// 连接设备（默认 LAZY 策略）
$conn = $manager->connect('plc-001');

// 复用已有连接
$conn = $manager->getConnection('plc-001');

// 断开连接
$manager->disconnect('plc-001');

// 单设备健康检查
$health = $manager->health('plc-001');

// 全部设备健康检查
$allHealth = $manager->healthAll();

// 列出所有活跃连接
$connections = $manager->getAllConnections();

// 关闭所有连接
$manager->shutdown();
```

### 连接策略

| 策略 | 行为说明 |
|----------|----------|
| `LazyStrategy`（默认）| 首次使用时建立连接，按设备 ID 缓存复用 |
| `EagerStrategy` | 启动时连接所有已配置设备 |
| `PooledStrategy` | 为每个设备维护连接池，轮询分配 |

### 事件列表

内核基于 PSR-14 分发以下生命周期事件：

| 事件类 | 触发时机 |
|-------|------------|
| `KernelBootedEvent` | 内核启动完成 |
| `ConnectionConnectedEvent` | 设备连接建立 |
| `ConnectionDisconnectedEvent` | 设备连接关闭 |
| `ConnectionStateChangedEvent` | 连接状态变更 |
| `ConnectionRetryEvent` | 重连尝试 |
| `DataReadEvent` | 数据读取完成 |
| `DataWriteEvent` | 数据写入完成 |
| `DataErrorEvent` | 数据操作出错 |
