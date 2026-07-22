# HART-IP 协议包 — HART over TCP/UDP，端口 5094

> [中文](README.md)

erikwang2013/industrial-protocols-hart-ip — 纯 PHP implementation, category: IoT / HART over IP.

## Installation

```bash
composer require erikwang2013/industrial-protocols-hart-ip
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

HART-IP 帧(9字节头封装HART命令)、TCP 通信、读取 PV/回路电流

## Architecture

TCP Socket(端口 5094) + HartIpFrame 帧编解码，实现 6 个 SDK 接口

## Protocol Support

HART-IP TCP (端口 5094)

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

