# MQTT 3.1.1 协议包 — 轻量级 IoT 消息协议，支持发布/订阅和通配符

> [English](README.en.md)

erikwang2013/industrial-protocols-mqtt — 纯 PHP 实现，类别：IoT / 消息协议。

## 安装

```bash
composer require erikwang2013/industrial-protocols-mqtt
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

MQTT 3.1.1 协议、CONNECT/PUBLISH(QoS 0-2)/SUBSCRIBE/PING/DISCONNECT、主题通配符 +/#、Remaining Length 编码、CONNACK 握手

## 架构

TCP Socket + MqttFrame 包编解码 + MqttDriver 驱动(CONNECT/CONNACK 握手)，实现 6 个 SDK 接口

## 协议支持

MQTT 3.1.1 TCP (端口 1883)

## 系统要求

- PHP >= 8.1
- Composer
- erikwang2013/industrial-protocols-kernel

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
