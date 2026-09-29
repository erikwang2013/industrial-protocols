# 框架集成指南

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

注册多个协议后再启动：

```php
$kernel->getProtocolRegistry()
    ->register(new ModbusProtocol())
    ->register(new BacnetProtocol())
    ->register(new EtherNetIPProtocol());
```

Plain PHP 适配器会在未检测到任何已知框架时自动回退生效。

---

## Laravel

### 安装

发布配置文件：

```bash
php artisan vendor:publish --tag=industrial-protocols-config
```

生成 `config/industrial-protocols.php`。

### 服务注册

`IndustrialProtocolsServiceProvider` 自动将 Kernel 注册为单例并绑定到 `'industrial-protocols'`。`vendor:publish` 时发布配置文件，同时注册 Artisan 命令。

### 注册协议

在 `AppServiceProvider` 中注册协议实现：

```php
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;

public function boot(): void
{
    $kernel = app(Kernel::class);
    $kernel->getProtocolRegistry()->register(new ModbusProtocol());
    $kernel->boot();
}
```

### 使用 Facade

```php
use Erikwang2013\IndustrialProtocols\Framework\Laravel\IndustrialProtocolsFacade;

$result = IndustrialProtocolsFacade::connect('plc-001')->read('40001');
```

Facade 代理到 `app('industrial-protocols')`，返回已启动的 Kernel 实例。

### Artisan 命令

```bash
# 连接设备并显示健康状态
php artisan industrial:connect plc-001

# 列出所有网关规则
php artisan industrial:gateway:list
```

### Octane 兼容

Laravel 适配器通过 `Laravel\Octane\Octane` 检测 Octane 并配置内核以适配常驻运行。

---

## Webman

### 自动发现

配置通过 `config/plugin/erikwang2013/industrial-protocols-kernel/` 自动发现。在此路径下创建配置文件即可。

### ProtocolProcess

`ProtocolProcess` 在 Worker 启动时自动初始化：

```php
// config/plugin/erikwang2013/industrial-protocols-kernel/process.php
return [
    'protocol' => [
        'handler' => Erikwang2013\IndustrialProtocols\Framework\Webman\ProtocolProcess::class,
    ],
];
```

协议自动发现扫描 `vendor/composer/installed.json` 中的已安装协议包。

### 使用方式

```php
$kernel = \Erikwang2013\IndustrialProtocols\Kernel::getInstance(); // 单例访问
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

---

## Hyperf

### ConfigProvider

内核通过 `composer.json` 的 `extra.hyperf.config` 声明 `ConfigProvider`，`composer require` 后 Hyperf 会自动读取并合并，**无需手动绑定**：

```php
'dependencies' => [
    Kernel::class => static fn (): Kernel => KernelFactory::create(),
],
'commands' => [ConnectCommand::class, GatewayListCommand::class],
```

配置放在 Hyperf 约定的合并目录 `config/autoload/industrial-protocols.php`。该文件不存在时回退到内核自带的默认配置，因此 `composer require` 后即可启动。

### DI 容器访问

```php
use Erikwang2013\IndustrialProtocols\Kernel;
use Hyperf\Context\ApplicationContext;

$kernel = ApplicationContext::getContainer()->get(Kernel::class);
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

### 命令

```bash
php bin/hyperf.php industrial:connect plc-001
php bin/hyperf.php industrial:gateway:list
```

Hyperf 适配器利用协程支持和连接池策略，在常驻 Worker 中获得最佳性能。

---

## ThinkPHP

### 服务启动

```php
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;

$kernel = IndustrialProtocolsService::boot();
$conn = $kernel->getConnectionManager()->connect('plc-001');

// 后续获取单例
$kernel = IndustrialProtocolsService::kernel();

// 使用完毕后关闭
IndustrialProtocolsService::shutdown();
```

服务类内部维护一个静态 Kernel 单例。协议自动发现扫描 `vendor/composer/installed.json`。

---

## Yii2

### Bootstrap 注册

将 Bootstrap 类添加到 Yii2 应用配置：

```php
'bootstrap' => [
    'industrial-protocols' => Erikwang2013\IndustrialProtocols\Framework\Yii2\Bootstrap::class,
],
```

### 使用组件

```php
$kernel = Yii::$app->get('industrial-protocols');
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

Bootstrap 类自动从 `vendor/composer/installed.json` 发现协议，并将 Kernel 注册为 Yii 应用组件。

---

## Yii3

Yii3 没有 `Yii::$app` 这样的全局应用对象，Kernel 通过 DI 容器构建。

### 注册 Kernel

在 `config/common/di.php` 中把 `Kernel` 指向工厂类：

```php
use Erikwang2013\IndustrialProtocols\Framework\Yii3\KernelFactory;
use Erikwang2013\IndustrialProtocols\Kernel;

return [
    Kernel::class => [KernelFactory::class, 'create'],
];
```

Yii3 容器默认共享实例，因此 `$container->get(Kernel::class)` 拿到的就是同一个已启动的 Kernel。

### 使用

```php
$kernel = $container->get(Kernel::class);
$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
```

工厂默认按**工作目录**定位 `config/industrial-protocols.php` 与 `vendor/composer/installed.json`。
若应用根目录不是工作目录（例如 Web 入口在 `public/`），显式传入路径：

```php
use Erikwang2013\IndustrialProtocols\Framework\Yii3\KernelFactory;
use Erikwang2013\IndustrialProtocols\Kernel;

return [
    Kernel::class => static fn (): Kernel => KernelFactory::create(
        dirname(__DIR__, 2) . '/config/industrial-protocols.php',
    ),
];
```

### 常驻进程

RoadRunner 下适配器通过 `RR_MODE` 环境变量识别常驻 Worker，Swoole 下通过扩展探测，`isLongRunning()` 返回 `true`。

---

## 框架检测

内核按以下优先级自动检测运行的框架：

```
Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Yii3 → Plain PHP
```

每个适配器实现 `FrameworkAdapterInterface`：

| 方法 | 用途 |
|--------|---------|
| `detect()` | 当前是否该框架 |
| `getName()` | 框架标识字符串 |
| `registerConfig()` | 注册/发布配置文件 |
| `registerServices()` | 注册容器绑定 |
| `registerCommands()` | 注册 CLI 命令 |
| `getConfigPath()` | 返回解析后的配置路径 |
| `isLongRunning()` | Swoole/Octane/Workerman 环境返回 true |
