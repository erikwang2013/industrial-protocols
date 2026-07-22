# EtherNet/IP 协议包 — ENIP 会话管理 + CIP Read Tag，TCP 通信

> [中文](README.md)

erikwang2013/industrial-protocols-ethernet-ip — 纯 PHP implementation, category: Industrial Ethernet.

## Installation

```bash
composer require erikwang2013/industrial-protocols-ethernet-ip
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

ENIP 会话注册/注销、CIP Read Tag 服务、TCP 通信

## Architecture

TCP Socket + ENIP 帧封装(24字节头) + CIP 协议，实现 6 个 SDK 接口

## Protocol Support

EtherNet/IP TCP (端口 44818)

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
