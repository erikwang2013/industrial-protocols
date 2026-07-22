# DNP3 协议包 — 电力自动化通信，Class 0 轮询，CRC-16/DNP 校验

> [中文](README.md)

erikwang2013/industrial-protocols-dnp3 — 纯 PHP implementation, category: Fieldbus / 电力自动化.

## Installation

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-dnp3
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

DNP3 帧编解码(CRC-16/DNP 校验)、Class 0 轮询、Select-Before-Operate 模式、TCP/串口双传输

## Architecture

TCP Socket / 串口 + Dnp3Frame 帧编解码 + Dnp3Driver 驱动，实现 6 个 SDK 接口

## Protocol Support

DNP3 TCP (端口 20000) / 串口

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

