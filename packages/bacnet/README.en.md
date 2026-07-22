# BACnet/IP 协议包 — 支持 Who-Is/I-Am 设备发现和 ReadProperty，UDP 通信

> [中文](README.md)

erikwang2013/industrial-protocols-bacnet — 纯 PHP implementation, category: Industrial Ethernet / Building Automation.

## Installation

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-bacnet
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

Who-Is/I-Am 设备发现、ReadProperty 读取属性、BVLC/NPDU 帧编解码、UDP 通信

## Architecture

UDP Socket + BVLC 帧封装 + NPDU 网络层，实现 6 个 SDK 接口

## Protocol Support

BACnet/IP (端口 47808)

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz


---

## Related Links

- [Industrial Protocols Main Project](https://github.com/erikwang2013/industrial-protocols)
- [Kernel](https://github.com/erikwang2013/industrial-protocols-kernel)
- [All 42 Protocol Packages](https://github.com/erikwang2013/industrial-protocols#supported-protocols)

