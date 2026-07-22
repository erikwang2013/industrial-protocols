# CC-Link RS-485 协议包 — 主从轮询，CRC-16/XMODEM 校验

> [English](README.en.md)

erikwang2013/industrial-protocols-cclink — 纯 PHP 实现，类别：现场总线。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-cclink
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

CC-Link 帧编解码(站号+标志+数据)、RS-485 串口通信(156k-10M bps)、CRC-16/XMODEM 校验

## 架构

RS-485 串口 + CcLinkFrame 帧编解码 + CcLinkDriver 驱动，实现 6 个 SDK 接口

## 协议支持

CC-Link RS-485 (156k-10M bps)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
