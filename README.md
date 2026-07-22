# K-Line 协议包 — OBD-II 诊断，ISO 9141/14230，5-baud 初始化

> [English](README.en.md)

erikwang2013/industrial-protocols-k-line — 纯 PHP 实现，类别：汽车总线 / OBD-II。

## 安装

```bash
composer require erikwang2013/industrial-protocols-k-line
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

ISO 9141/14230 (K-Line)、5-baud 初始化序列、OBD-II PID 请求(010C/010D/0105)、10400 baud UART

## 架构

UART 串口 + 5-baud 初始化 + KLineFrame 帧编解码，实现 6 个 SDK 接口

## 协议支持

K-Line ISO 9141/14230 (10400 baud)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
