# ControlNet 协议包 — Allen-Bradley 同轴令牌环，需专用接口桥接

> [English](README.en.md)

erikwang2013/industrial-protocols-controlnet — Bridge 实现，类别：需专用硬件。

## 安装

```bash
composer require erikwang2013/industrial-protocols-controlnet
```

## 使用

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// 通过 ConnectionManager 连接设备
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

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
