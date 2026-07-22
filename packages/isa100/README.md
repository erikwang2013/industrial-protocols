# ISA100.11a 协议包 — 工业无线 mesh，需 ISA100 网关桥接

> [English](README.en.md)

erikwang2013/industrial-protocols-isa100 — Bridge 实现，类别：需专用硬件。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-isa100
```

> 本包依赖 [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols)，内核提供连接管理、协议注册、协程适配、事件系统等基础设施。

## 使用

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// 通过 ConnectionManager 连接设备
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

> 本包依赖 [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols)，内核提供连接管理、协议注册、协程适配、事件系统等基础设施。

## 功能

通过 BridgeInterface 桥接至厂商 C/C++ SDK 或网关硬件(ExternalProcessBridge / TcpGatewayBridge)，实现 6 个 SDK 接口，由 BridgeConnector 统一代理。

## 架构

Bridge 桥接模式：BridgeConnector 实现 ConnectorInterface，内部委托给 BridgeInterface(open/close/execute/isReady)。支持 ExternalProcessBridge(本地SDK子进程,proc_open)和 TcpGatewayBridge(远程网关TCP)。

## 协议支持

需对应厂商 SDK 或网关硬件(Anybus/Hilscher/Moxa 等，参见 docs/vendors.md)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
