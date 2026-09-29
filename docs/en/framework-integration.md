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

### ConfigProvider

The kernel declares its `ConfigProvider` through `composer.json` `extra.hyperf.config`, so Hyperf reads and
merges it automatically after `composer require` — **no manual binding**:

```php
'dependencies' => [
    Kernel::class => static fn (): Kernel => KernelFactory::create(),
],
'commands' => [ConnectCommand::class, GatewayListCommand::class],
```

Configuration goes in Hyperf's merge directory, `config/autoload/industrial-protocols.php`. When that file
does not exist the kernel falls back to its own bundled defaults, so it boots straight after `composer require`.

### Container access

```php
use Erikwang2013\IndustrialProtocols\Kernel;
use Hyperf\Context\ApplicationContext;

$kernel = ApplicationContext::getContainer()->get(Kernel::class);
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

### Commands

```bash
php bin/hyperf.php industrial:connect plc-001
php bin/hyperf.php industrial:gateway:list
```

The Hyperf adapter leans on coroutine support and pooled connection strategies for best throughput in a
persistent worker.

## ThinkPHP

```php
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;
$kernel = IndustrialProtocolsService::boot();
```

## Yii2

```php
$kernel = Yii::$app->get('industrial-protocols');
```

## Yii3

Yii3 has no global application object such as `Yii::$app`, so the Kernel is built through the DI container.

### Registering the Kernel

Point `Kernel` at the factory class in `config/common/di.php`:

```php
use Erikwang2013\IndustrialProtocols\Framework\Yii3\KernelFactory;
use Erikwang2013\IndustrialProtocols\Kernel;

return [
    Kernel::class => [KernelFactory::class, 'create'],
];
```

The Yii3 container is shared by default, so `$container->get(Kernel::class)` returns the same booted kernel every time.

### Usage

```php
$kernel = $container->get(Kernel::class);
$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
```

The factory resolves `config/industrial-protocols.php` and `vendor/composer/installed.json` relative to the
**working directory**. When the application root is not the working directory (a web entry script under
`public/`, for example), pass the paths explicitly:

```php
use Erikwang2013\IndustrialProtocols\Framework\Yii3\KernelFactory;
use Erikwang2013\IndustrialProtocols\Kernel;

return [
    Kernel::class => static fn (): Kernel => KernelFactory::create(
        dirname(__DIR__, 2) . '/config/industrial-protocols.php',
    ),
];
```

### Long-running processes

Under RoadRunner the adapter detects a persistent worker through the `RR_MODE` environment variable,
and under Swoole by extension detection; `isLongRunning()` returns `true` in both cases.

---

## Framework Detection Priority

`Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Yii3 → Plain PHP`
