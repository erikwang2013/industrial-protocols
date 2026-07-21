# 安全指南

> [English](en/security.md)

## 输入校验

使用 `InputValidator` 对所有对外 API 输入进行校验。所有方法均为静态方法。

```php
use Erikwang2013\IndustrialProtocols\Security\InputValidator;

// 设备 ID：字母数字 + 短横线 + 下划线 + 点号，最长 128 字符
$deviceId = InputValidator::deviceId($userInput);

// 主机地址：IP 地址或主机名，最长 255 字符
$host = InputValidator::host($config['host']);

// 端口号：1-65535
$port = InputValidator::port($config['port']);

// Modbus 寄存器地址：0-65535
$address = InputValidator::modbusAddress('40001');

// 超时时间：10-60000 毫秒
$timeout = InputValidator::timeout(3000);

// 帧大小校验：按协议最大值检查
InputValidator::frameSize($rawBytes, 260);   // Modbus TCP：260 字节
InputValidator::frameSize($rawBytes, 4096);  // BACnet：4096 字节
InputValidator::frameSize($rawBytes, 65535); // EtherNet/IP：65535 字节
```

所有校验方法在校验失败时抛出 `\InvalidArgumentException`。

## 最佳实践

### 网络安全

1. **绝不要将工业协议端口暴露到公网** — Modbus、BACnet、EtherNet/IP 没有内置加密或认证机制
2. **使用防火墙规则**限制设备访问只允许受信任的 IP 地址
3. **隔离 OT 网络**，通过 VLAN 或物理隔离与 IT 办公网络分开
4. **需要远程访问工业设备时使用 VPN**（WireGuard、OpenVPN、IPSec）

### 配置安全

1. **使用 `InputValidator` 校验所有用户配置**后再使用
2. **使用 `DatabaseConfigRepository`** 配合参数化查询（PDO 预处理语句）存储设备配置
3. **不要硬编码凭据** — 使用环境变量或密钥管理服务
4. **设置合理的超时时间**（默认 3000ms），防止挂起的连接耗尽资源

### 运行安全

1. **监控熔断器事件** — 重复跳闸表明设备或网络存在持续性问题需要排查
2. **设置合理的重试上限**（`default_retry_max`），避免重连风暴
3. **使用指数退避**进行重连（`default_retry_backoff: 'exponential'`）
4. **配置健康检查间隔**（`health_check_interval`）主动发现失效连接
5. **OPC UA 证书定期轮换**（OPC UA 支持上线后）

## 帧大小限制

所有协议帧均按协议规定的最大值校验：

| 协议 | 最大帧大小 |
|----------|-------------------|
| Modbus TCP | 260 字节 |
| BACnet | 4096 字节 |
| EtherNet/IP | 65535 字节 |

## 敏感数据日志

内核的 `PsrLogDriver` 委托给任意 PSR-3 兼容日志器。配置日志器时应：

- 生产环境**脱敏 IP 地址**
- 如需数据保密则**脱敏寄存器数值**
- 生产环境**避免记录原始协议帧** — DEBUG 级别用于详细通信数据，正式运行中关闭

## 配置示例

```php
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
    'health_check_interval' => 30,
    'default_retry_max' => 3,
    'default_retry_backoff' => 'exponential',
    'default_timeout' => 3000,
];
```

## 异常参考

本库针对所有故障模式抛出带类型的异常：

| 异常类 | 原因 |
|-----------|-------|
| `ConnectionException` | 通用连接故障 |
| `ConnectionTimeoutException` | 连接尝试超时 |
| `ConnectionRefusedException` | 远端设备拒绝连接 |
| `ConnectionClosedException` | 运行中连接丢失 |
| `AddressOutOfRangeException` | 寄存器地址超出有效范围 |
| `FrameException` | 协议帧格式异常或损坏 |
| `CrcException` | CRC 校验失败（Modbus RTU） |
| `DeviceBusyException` | 设备返回忙状态 |
| `DeviceException` | 设备报告错误 |
| `ProtocolException` | 协议级错误 |
