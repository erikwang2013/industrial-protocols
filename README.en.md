# Modbus TCP/RTU/ASCII 协议包 — 支持 FC 01/03/04/06/10，纯 PHP Socket 实现

> [中文](README.md)

erikwang2013/industrial-protocols-modbus — 纯 PHP implementation, category: Industrial Ethernet / Fieldbus.

## Installation

```bash
composer require erikwang2013/industrial-protocols-modbus
```

## Usage

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// Connect via ConnectionManager
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

## Features

Modbus TCP (FC 01/03/04/06/10)、Modbus RTU (RS-485 串口 + CRC16)、Modbus ASCII、保持寄存器/输入寄存器/线圈读写、地址解析(40001-49999/30001-39999)

## Architecture

TCP 驱动(stream_socket_client) + RTU 驱动(串口 fopen + stty) + 帧编解码(ModbusFrame/ModbusRequest/ModbusResponse)，实现 6 个 SDK 接口

## Protocol Support

Modbus TCP (端口 502)、Modbus RTU (RS-485)、Modbus ASCII

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
