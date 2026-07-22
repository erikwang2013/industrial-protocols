# Modbus TCP/RTU/ASCII 协议包 — 支持 FC 01/03/04/06/10，纯 PHP Socket 实现

> [English](README.en.md)

erikwang2013/industrial-protocols-modbus — 纯 PHP 实现，类别：工业以太网 / 现场总线。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-modbus
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

Modbus TCP (FC 01/03/04/06/10)、Modbus RTU (RS-485 串口 + CRC16)、Modbus ASCII、保持寄存器/输入寄存器/线圈读写、地址解析(40001-49999/30001-39999)

## 架构

TCP 驱动(stream_socket_client) + RTU 驱动(串口 fopen + stty) + 帧编解码(ModbusFrame/ModbusRequest/ModbusResponse)，实现 6 个 SDK 接口

## 协议支持

Modbus TCP (端口 502)、Modbus RTU (RS-485)、Modbus ASCII

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

