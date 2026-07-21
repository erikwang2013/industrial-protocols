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
