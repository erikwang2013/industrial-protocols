# HART 协议包 — 4-20mA FSK 调制解调器通信，支持 PV/回路电流/设备信息读取

> [English](README.en.md)

erikwang2013/industrial-protocols-hart — 纯 PHP 实现，类别：现场总线。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-hart
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

HART 帧编解码(前导码+定界符+地址+命令+数据+校验和)、FSK 1200 baud 串口通信、读取主变量(PV)/回路电流/设备信息

## 架构

串口(UART via HART modem) + HartFrame 帧编解码 + HartDriver 驱动，实现 6 个 SDK 接口

## 协议支持

HART 4-20mA FSK (HART 调制解调器)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
