# 硬件厂商适配参考

> [English](en/vendors.md)

本库通过 Bridge 层适配以下主流工业硬件厂商的 C/C++ SDK 或网关设备。

## 厂商一览

| 厂商 | 协议 | Bridge 类型 | SDK/接口 |
|------|------|-----------|---------|
| Beckhoff | EtherCAT | ExternalProcessBridge | TwinCAT ADS |
| Siemens | PROFINET | TcpGatewayBridge | Open Communication |
| B&R | POWERLINK | ExternalProcessBridge | Automation Studio / openPOWERLINK |
| Bosch Rexroth | SERCOS III | TcpGatewayBridge | ctrlX CORE / netX |
| Hilscher | 多协议 | TcpGatewayBridge | netX SoC |
| HMS/Anybus | 多协议 | TcpGatewayBridge | Anybus Communicator |
| Moxa | 多协议 | TcpGatewayBridge | MGate 系列 |
| Phoenix Contact | PROFINET/EIP | TcpGatewayBridge | AXL F 系列 |

## 架构

厂商适配层基于 Bridge 接口 (`BridgeInterface`) 构建，提供两种桥接方式：

- **ExternalProcessBridge** — 通过启动 C/C++ SDK 进程通信（`proc_open`），适用于本地 SDK
- **TcpGatewayBridge** — 通过 TCP/UDP 连接硬件网关设备（`stream_socket_client`），适用于远程/嵌入式网关

### 关键类

| 类 | 用途 |
|---|------|
| `VendorProfile` | 单个厂商的配置描述（名称、协议、桥接类型、SDK 路径、默认端口、设备列表） |
| `DeviceProfile` | 单个设备型号的配置描述（型号、版本、配置覆盖项） |
| `VendorBridgeFactory` | 厂商注册表和桥接创建工厂 |
| `DefaultVendors` | 预置的 8 大主流工业硬件厂商配置 |

## 使用方法

### 获取 Kernel 注册的厂商工厂

```php
$kernel = new Kernel();
$kernel->boot();

$factory = $kernel->getVendorBridgeFactory();
```

### 列出所有厂商

```php
foreach ($factory->listVendors() as $name => $vendor) {
    echo "$name: {$vendor->protocol} via {$vendor->bridgeType}\n";
}
```

### 创建厂商桥接

```php
// Beckhoff — ExternalProcessBridge
$bridge = $factory->create('beckhoff', 'CX2030', '3.1');

// Siemens — TcpGatewayBridge
$bridge = $factory->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
]);

// 从连接配置数组创建
$bridge = $factory->createFromConfig([
    'vendor' => 'hilscher',
    'device_model' => 'netX 90',
    'host' => '10.0.0.10',
    'port' => 5000,
]);
```

## 各厂商详情

### Beckhoff (EtherCAT)

基于 TwinCAT ADS 协议。桥接类型为 `external-process`，需本地安装 TwinCAT ADS DLL（路径：`/opt/beckhoff/twincat/AdsApi/TcAdsDll`）。

支持设备：CX2030, CX5140, C6015, C6030, EK1100, EK1501

```php
$beckhoff = $factory->getVendor('beckhoff');
$device = $beckhoff->getDevice('CX2030');
// $device->configOverrides: ['ads_netid' => '0.0.0.0.1.1']
```

### Siemens (PROFINET)

基于 SIMATIC Open Communication。桥接类型为 `tcp-gateway`，默认端口 34964。

支持设备：S7-1200, S7-1500, ET 200SP, ET 200MP, S7-400

```php
$bridge = $factory->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
]);
```

### B&R Automation (POWERLINK)

支持 Automation Studio SDK 和 openPOWERLINK 两种模式。X20BC0083 设备默认使用 openPOWERLINK 路径。

支持设备：X20CP1584, X20CP1586, ACOPOS P3, X20BC0083

### Bosch Rexroth (SERCOS III)

通过 ctrlX CORE 或 Hilscher netX 网关连接 SERCOS III 设备。ctrlX CORE 使用 HTTPS 端口 8443。

支持设备：IndraDrive Cs, IndraDrive Mi, ctrlX CORE, HMV01

### Hilscher (多协议)

netX 系列 SoC 支持 EtherCAT、PROFINET、POWERLINK、SERCOS III、EtherNet/IP 多种协议，默认端口 5000。

支持设备：netX 90, netX 4000, cifX RE, comX

### HMS Anybus (多协议)

协议转换网关，支持 EtherNet/IP 到 Modbus/PROFIBUS/PROFINET 转换。默认端口 502。

支持设备：Anybus Communicator, Anybus X-gateway, Anybus CompactCom, Anybus Wireless Bolt

### Moxa (多协议)

工业以太网网关 MGate 系列，支持 Modbus、PROFINET、EtherNet/IP 协议转换。默认端口 502。

支持设备：MGate 5101-PBM-MN, MGate 5102-PBM-PN, MGate 5105-MB-EIP, MGate 5118

### Phoenix Contact (PROFINET / EtherNet/IP)

AXL F 系列 I/O 系统，PROFINET 设备默认端口 34964，EtherNet/IP 设备默认端口 44818。

支持设备：AXL F BK PN, AXL F IL ETH, AXL E ETH DI16, ILC 191

## 自定义厂商

通过 `VendorBridgeFactory::register()` 注册自定义厂商配置：

```php
$factory->register(new VendorProfile(
    name: 'my-custom-vendor',
    protocol: 'ethercat',
    bridgeType: 'external-process',
    sdkPath: '/opt/mycompany/bin/ethercat_master',
    defaultPort: 0,
    devices: [
        new DeviceProfile('MyDevice', 'V1.0', ['ads_netid' => '0.0.0.0.1.1']),
    ],
));
```
