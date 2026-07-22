# Profinet NRT 协议包 — DCP 设备发现 + Record Data 读写（RT 通道需 ERTEC 硬件）

> [English](README.en.md)

erikwang2013/industrial-protocols-profinet — 纯 PHP (NRT) 实现，类别：工业以太网。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-profinet
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

DCP 设备发现(Identify 广播)、Read Record / Write Record(非实时通道)、UDP/TCP 双传输、Profinet 帧编解码(0xFEFE DCP / 0xFEFD Record Data)

## 架构

UDP Socket(DCP) + TCP Socket(Record Data) + ProfinetFrame 帧编解码，实现 6 个 SDK 接口。RT 实时通道需 ERTEC 硬件芯片

## 协议支持

Profinet NRT 非实时通道 (端口 34964)、DCP 发现、Record Data 读写

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
