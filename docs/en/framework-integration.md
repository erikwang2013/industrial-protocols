# Framework Integration Guide

> [中文](../framework-integration.md)

## Plain PHP

```php
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

require 'vendor/autoload.php';

$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
$kernel->shutdown();
```

## Laravel

```bash
php artisan vendor:publish --tag=industrial-protocols-config
```

```php
use Erikwang2013\IndustrialProtocols\Framework\Laravel\IndustrialProtocolsFacade;
$result = IndustrialProtocolsFacade::connect('plc-001')->read('40001');
```

```bash
php artisan industrial:connect plc-001
php artisan industrial:gateway:list
```

## Webman

Auto-discovered via `config/plugin/`. ProtocolProcess auto-boots on worker start.

## Hyperf

```php
$kernel = ApplicationContext::getContainer()->get(Kernel::class);
```

```bash
php bin/hyperf.php industrial:connect plc-001
```

## ThinkPHP

```php
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;
$kernel = IndustrialProtocolsService::boot();
```

## Yii2

```php
$kernel = Yii::$app->get('industrial-protocols');
```

## Framework Detection Priority

`Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Plain PHP`
