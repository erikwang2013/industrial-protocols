# IEC 61850 协议包 — 变电站自动化，MMS over TCP，IED 数据路径解析

> [中文](README.md)

erikwang2013/industrial-protocols-iec61850 — 纯 PHP (MMS) implementation, category: Fieldbus / 变电站自动化.

## Installation

```bash
composer require erikwang2013/industrial-protocols-iec61850
```

## Usage

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// Connect via ConnectionManager
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

## Features

MMS over TPKT(TCP)、IED 数据路径解析(LD/LN.FC.DO.DA)、Initiate/Conclude 会话管理、GOOSE/SV 需 Bridge 桥接

## Architecture

TCP Socket(端口 102) + TPKT 传输 + MMS 编解码，实现 6 个 SDK 接口

## Protocol Support

IEC 61850 MMS TCP (端口 102)

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
