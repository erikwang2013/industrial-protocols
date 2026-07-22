# MQTT 3.1.1 协议包 — 轻量级 IoT 消息协议，支持发布/订阅和通配符

> [中文](README.md)

erikwang2013/industrial-protocols-mqtt — 纯 PHP implementation, category: IoT / Messaging.

## Installation

```bash
composer require erikwang2013/industrial-protocols-kernel erikwang2013/industrial-protocols-mqtt
```

> This package depends on [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols), which provides connection management, protocol registry, coroutine adaptation, event system and more.

## Usage

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();

// Connect via ConnectionManager
$conn = $kernel->getConnectionManager()->connect('device-id');
$result = $conn->read('address');
```

> This package depends on [erikwang2013/industrial-protocols-kernel](https://github.com/erikwang2013/industrial-protocols), which provides connection management, protocol registry, coroutine adaptation, event system and more.

## Features

MQTT 3.1.1 协议、CONNECT/PUBLISH(QoS 0-2)/SUBSCRIBE/PING/DISCONNECT、主题通配符 +/#、Remaining Length 编码、CONNACK 握手

## Architecture

TCP Socket + MqttFrame 包编解码 + MqttDriver 驱动(CONNECT/CONNACK 握手)，实现 6 个 SDK 接口

## Protocol Support

MQTT 3.1.1 TCP (端口 1883)

## Requirements

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
