# Profinet NRT 协议包 — DCP 设备发现 + Record Data 读写（RT 通道需 ERTEC 硬件）

> [中文](README.md)

erikwang2013/industrial-protocols-profinet — 纯 PHP (NRT) implementation, category: Industrial Ethernet.

## Installation

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-profinet
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

DCP 设备发现(Identify 广播)、Read Record / Write Record(非实时通道)、UDP/TCP 双传输、Profinet 帧编解码(0xFEFE DCP / 0xFEFD Record Data)

## Architecture

UDP Socket(DCP) + TCP Socket(Record Data) + ProfinetFrame 帧编解码，实现 6 个 SDK 接口。RT 实时通道需 ERTEC 硬件芯片

## Protocol Support

Profinet NRT 非实时通道 (端口 34964)、DCP 发现、Record Data 读写

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
