# BACnet/IP 协议包 — 支持 Who-Is/I-Am 设备发现和 ReadProperty，UDP 通信

> [English](README.en.md)

erikwang2013/industrial-protocols-bacnet — 纯 PHP 实现，类别：工业以太网 / 楼宇自动化。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-bacnet
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

Who-Is/I-Am 设备发现、ReadProperty 读取属性、BVLC/NPDU 帧编解码、UDP 通信

## 架构

UDP Socket + BVLC 帧封装 + NPDU 网络层，实现 6 个 SDK 接口

## 协议支持

BACnet/IP (端口 47808)

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

