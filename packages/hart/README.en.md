# HART 协议包 — 4-20mA FSK 调制解调器通信，支持 PV/回路电流/设备信息读取

> [中文](README.md)

erikwang2013/industrial-protocols-hart — 纯 PHP implementation, category: Fieldbus.

## Installation

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-hart
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

HART 帧编解码(前导码+定界符+地址+命令+数据+校验和)、FSK 1200 baud 串口通信、读取主变量(PV)/回路电流/设备信息

## Architecture

串口(UART via HART modem) + HartFrame 帧编解码 + HartDriver 驱动，实现 6 个 SDK 接口

## Protocol Support

HART 4-20mA FSK (HART 调制解调器)

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

