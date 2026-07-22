# EtherNet/IP 协议包 — ENIP 会话管理 + CIP Read Tag，TCP 通信

> [English](README.en.md)

erikwang2013/industrial-protocols-ethernet-ip — 纯 PHP 实现，类别：工业以太网。

## 安装

```bash
composer require erikwang2013/industrial-protocols-ethernet-ip
```

> 本包依赖 [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols)，内核提供连接管理、协议注册、协程适配、事件系统等基础设施。

## 使用

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// 通过 ConnectionManager 连接设备
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

> 本包依赖 [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols)，内核提供连接管理、协议注册、协程适配、事件系统等基础设施。

## 功能

ENIP 会话注册/注销、CIP Read Tag 服务、TCP 通信

## 架构

TCP Socket + ENIP 帧封装(24字节头) + CIP 协议，实现 6 个 SDK 接口

## 协议支持

EtherNet/IP TCP (端口 44818)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
