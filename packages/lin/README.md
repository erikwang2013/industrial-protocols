# LIN 协议包 — 汽车车身总线，19200 bps UART，主从模式

> [English](README.en.md)

erikwang2013/industrial-protocols-lin — 纯 PHP 实现，类别：汽车总线。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-lin
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

LIN 帧(Sync Break + Sync Field + PID + Data + Checksum)、PID 校验位编码、Classic/Enhanced 校验和、19200 bps UART

## 架构

UART 串口 + LinFrame 帧编解码 + LinDriver 驱动，实现 6 个 SDK 接口

## 协议支持

LIN 主从模式 (19200 bps)

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

