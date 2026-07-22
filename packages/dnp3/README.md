# DNP3 协议包 — 电力自动化通信，Class 0 轮询，CRC-16/DNP 校验

> [English](README.en.md)

erikwang2013/industrial-protocols-dnp3 — 纯 PHP 实现，类别：现场总线 / 电力自动化。

## 安装

```bash
composer require erikwang2013/industrial-protocols-dnp3
```

## 使用

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// 通过 ConnectionManager 连接设备
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

## 功能

DNP3 帧编解码(CRC-16/DNP 校验)、Class 0 轮询、Select-Before-Operate 模式、TCP/串口双传输

## 架构

TCP Socket / 串口 + Dnp3Frame 帧编解码 + Dnp3Driver 驱动，实现 6 个 SDK 接口

## 协议支持

DNP3 TCP (端口 20000) / 串口

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
