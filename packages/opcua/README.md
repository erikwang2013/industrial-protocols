# OPC UA Binary 协议包 — 完整 UA Binary 协议栈，支持 CreateSession/Read/Write/Browse

> [English](README.en.md)

erikwang2013/industrial-protocols-opc-ua — 纯 PHP 实现，类别：工业以太网。

## 安装

```bash
composer require erikwang2013/industrial-protocols-opc-ua
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

UA Binary 协议栈、NodeId(4种编码)、Variant(13种标量类型+数组)、BinaryEncoder/Decoder、Hello/Acknowledge 握手、SecureChannel(Open/Close)、Session(Create/Activate)、Read/Write/Browse 服务

## 架构

TCP Socket → Hello/Acknowledge → SecureChannel(SecurityPolicy:None) → Session(Anonymous) → Read/Write/Browse。完整 UA Binary 类型系统(NodeId/StatusCode/Variant)+ 二进制编解码器

## 协议支持

OPC UA Binary TCP (端口 4840)、Security Policy None、Anonymous 认证

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

