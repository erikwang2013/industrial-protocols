# Framework Integration Guide

> [English](en/framework-integration.md)

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

For multiple protocols, register each before booting:

```php
$kernel->getProtocolRegistry()
    ->register(new ModbusProtocol())
    ->register(new BacnetProtocol())
    ->register(new EtherNetIPProtocol());
```

The Plain PHP adapter auto-detects when no known framework is present.

---

## Laravel

### Installation

Publish the configuration file:

```bash
php artisan vendor:publish --tag=industrial-protocols-config
```

This creates `config/industrial-protocols.php`.

### Service Provider Registration

The `IndustrialProtocolsServiceProvider` auto-registers the Kernel as a singleton and binds it as `'industrial-protocols'`. It publishes config on `vendor:publish` and registers console commands.

### Register Protocols

In your `AppServiceProvider`, register protocol implementations:

```php
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;
use Erikwang2013\IndustrialProtocols\Bacnet\BacnetProtocol;

public function boot(): void
{
    $kernel = app(Kernel::class);
    $kernel->getProtocolRegistry()
        ->register(new ModbusProtocol())
        ->register(new BacnetProtocol());
    $kernel->boot();
}
```

### Using the Facade

```php
use Erikwang2013\IndustrialProtocols\Framework\Laravel\IndustrialProtocolsFacade;

$result = IndustrialProtocolsFacade::connect('plc-001')->read('40001');
```

The facade proxies to `app('industrial-protocols')` which returns the booted Kernel.

### Artisan Commands

```bash
# Connect to a device and show health
php artisan industrial:connect plc-001

# List all configured gateway rules
php artisan industrial:gateway:list
```

### Octane Compatibility

The Laravel adapter detects Octane via `Laravel\Octane\Octane` and configures the kernel for long-running operation.

---

## Webman

### Auto-Discovery

Configuration is auto-discovered from `config/plugin/industrial-protocols/kernel/`. Create the config file at that path.

### ProtocolProcess

The `ProtocolProcess` class auto-boots on worker start:

```php
// config/plugin/industrial-protocols/kernel/process.php
return [
    'protocol' => [
        'handler' => Erikwang2013\IndustrialProtocols\Framework\Webman\ProtocolProcess::class,
    ],
];
```

Protocol auto-discovery scans `vendor/composer/installed.json` for installed protocol packages.

### Usage

```php
$kernel = \Erikwang2013\IndustrialProtocols\Kernel::getInstance(); // singleton access
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

---

## Hyperf

### ConfigProvider

The ConfigProvider auto-registers DI bindings. Create configuration at `config/autoload/industrial-protocols.php`.

### DI Container Access

```php
use Erikwang2013\IndustrialProtocols\Kernel;
use Hyperf\Context\ApplicationContext;

$kernel = ApplicationContext::getContainer()->get(Kernel::class);
$kernel->boot();
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

### Commands

```bash
php bin/hyperf.php industrial:connect plc-001
php bin/hyperf.php industrial:gateway:list
```

The Hyperf adapter leverages coroutine support and pooled connection strategies for optimal performance in long-running workers.

---

## ThinkPHP

### Service Boot

```php
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;

$kernel = IndustrialProtocolsService::boot();
$conn = $kernel->getConnectionManager()->connect('plc-001');

// Access the singleton later
$kernel = IndustrialProtocolsService::kernel();

// Shutdown when done
IndustrialProtocolsService::shutdown();
```

The service class maintains a static Kernel singleton. Protocol auto-discovery scans `vendor/composer/installed.json`.

---

## Yii2

### Bootstrap Registration

Add the bootstrap class to your Yii2 application config:

```php
'bootstrap' => [
    'industrial-protocols' => Erikwang2013\IndustrialProtocols\Framework\Yii2\Bootstrap::class,
],
```

### Using the Component

```php
$kernel = Yii::$app->get('industrial-protocols');
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

The Bootstrap class auto-discovers protocols from `vendor/composer/installed.json` and sets the Kernel as a Yii application component.

---

## Framework Detection

The kernel auto-detects the active framework using the following precedence:

```
Laravel → Webman → ThinkPHP → Yii2 → Plain PHP
```

Each adapter implements `FrameworkAdapterInterface`:

| Method | Purpose |
|--------|---------|
| `detect()` | Returns true if the framework is active |
| `getName()` | Framework identifier string |
| `registerConfig()` | Config path registration |
| `registerServices()` | Service container registration |
| `registerCommands()` | Console command registration |
| `getConfigPath()` | Returns the resolved config path |
| `isLongRunning()` | True for Swoole/Octane/Workerman environments |
