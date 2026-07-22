# K-Line 协议包 — OBD-II 诊断，ISO 9141/14230，5-baud 初始化

> [中文](README.md)

erikwang2013/industrial-protocols-k-line — 纯 PHP implementation, category: Automotive Bus / OBD-II.

## Installation

```bash
composer require erikwang2013/industrial-protocols-k-line
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

ISO 9141/14230 (K-Line)、5-baud 初始化序列、OBD-II PID 请求(010C/010D/0105)、10400 baud UART

## Architecture

UART 串口 + 5-baud 初始化 + KLineFrame 帧编解码，实现 6 个 SDK 接口

## Protocol Support

K-Line ISO 9141/14230 (10400 baud)

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
