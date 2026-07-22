# IEC 61850 协议包 — 变电站自动化，MMS over TCP，IED 数据路径解析

> [English](README.en.md)

erikwang2013/industrial-protocols-iec61850 — 纯 PHP (MMS) 实现，类别：现场总线 / 变电站自动化。

## 安装

```bash
composer require erikwang2013/industrial-protocols-iec61850
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

MMS over TPKT(TCP)、IED 数据路径解析(LD/LN.FC.DO.DA)、Initiate/Conclude 会话管理、GOOSE/SV 需 Bridge 桥接

## 架构

TCP Socket(端口 102) + TPKT 传输 + MMS 编解码，实现 6 个 SDK 接口

## 协议支持

IEC 61850 MMS TCP (端口 102)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
