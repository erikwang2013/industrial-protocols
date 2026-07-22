# HART-IP 协议包 — HART over TCP/UDP，端口 5094

> [English](README.en.md)

erikwang2013/industrial-protocols-hart-ip — 纯 PHP 实现，类别：IoT / HART over IP。

## 安装

```bash
composer require erikwang2013/industrial-protocols-hart-ip
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

HART-IP 帧(9字节头封装HART命令)、TCP 通信、读取 PV/回路电流

## 架构

TCP Socket(端口 5094) + HartIpFrame 帧编解码，实现 6 个 SDK 接口

## 协议支持

HART-IP TCP (端口 5094)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz


---

## 相关链接

- [Industrial Protocols 主项目](https://github.com/erikwang2013/industrial-protocols)
- [Kernel 内核](https://github.com/erikwang2013/industrial-protocols-kernel)
- [全部 42 个协议包](https://github.com/erikwang2013/industrial-protocols#支持的协议)

