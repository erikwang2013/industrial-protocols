# Industrial Protocols Kernel

> [中文](README.md)

Micro-kernel — providing connection management, config management, gateway engine, event system, coroutine adaptation, framework adapters, hardware bridge layer, vendor profiles, and more.

## Installation

```bash
composer require erikwang2013/industrial-protocols-kernel
```

## Usage

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();
$conn = $kernel->getConnectionManager()->connect('device-id');
```

## Features

Protocol Registry (auto-discovery), ConnectionManager (3 strategies), ConfigRepository (3 implementations), GatewayEngine (poll/change/cron), CoroutineAdapter (Swoole/Fiber/Sync), FrameworkAdapters (6 frameworks), Bridge Layer, Vendor Profiles (12 vendors), Event System (PSR-14, 13 types), Log Drivers (PSR-3/File/Null), Retry (4 strategies), Metrics (Prometheus), Alerts (Webhook/Log), Security (InputValidator), Exceptions (20+ types)

## Requirements

- PHP >= 8.1
- Composer

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
