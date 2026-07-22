# ISA100.11a 协议包 — 工业无线 mesh，需 ISA100 网关桥接

> [中文](README.md)

erikwang2013/industrial-protocols-isa100 — Bridge implementation, category: Hardware-Dependent (Bridge).

## Installation

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-isa100
```

> This package depends on [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols), which provides connection management, protocol registry, coroutine adaptation, event system and more.

## Usage

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// Connect via ConnectionManager
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

> This package depends on [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols), which provides connection management, protocol registry, coroutine adaptation, event system and more.

## Features

通过 BridgeInterface 桥接至厂商 C/C++ SDK 或网关硬件(ExternalProcessBridge / TcpGatewayBridge)，实现 6 个 SDK 接口，由 BridgeConnector 统一代理。

## Architecture

Bridge 桥接模式：BridgeConnector 实现 ConnectorInterface，内部委托给 BridgeInterface(open/close/execute/isReady)。支持 ExternalProcessBridge(本地SDK子进程,proc_open)和 TcpGatewayBridge(远程网关TCP)。

## Protocol Support

需对应厂商 SDK 或网关硬件(Anybus/Hilscher/Moxa 等，参见 docs/vendors.md)

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
