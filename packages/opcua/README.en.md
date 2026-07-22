# OPC UA Binary 协议包 — 完整 UA Binary 协议栈，支持 CreateSession/Read/Write/Browse

> [中文](README.md)

erikwang2013/industrial-protocols-opc-ua — 纯 PHP implementation, category: Industrial Ethernet.

## Installation

```bash
composer require erikwang2013/industrial-protocols-opc-ua
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

UA Binary 协议栈、NodeId(4种编码)、Variant(13种标量类型+数组)、BinaryEncoder/Decoder、Hello/Acknowledge 握手、SecureChannel(Open/Close)、Session(Create/Activate)、Read/Write/Browse 服务

## Architecture

TCP Socket → Hello/Acknowledge → SecureChannel(SecurityPolicy:None) → Session(Anonymous) → Read/Write/Browse。完整 UA Binary 类型系统(NodeId/StatusCode/Variant)+ 二进制编解码器

## Protocol Support

OPC UA Binary TCP (端口 4840)、Security Policy None、Anonymous 认证

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
