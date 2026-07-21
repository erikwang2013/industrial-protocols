# Industrial Protocols PHP

PHP 工业网络通信协议插件 —— 微内核 + 协议 SDK 架构，支持 Modbus、BACnet、EtherNet/IP 等主流工业协议，兼容 Plain PHP、Laravel、Webman、Hyperf、ThinkPHP、Yii2 六大运行环境。

---

## 目录

- [功能清单](#功能清单)
- [支持的工业通信协议](#支持的工业通信协议)
- [支持的框架](#支持的框架)
- [快速开始](#快速开始)
- [使用说明](#使用说明)
- [协议使用示例](#协议使用示例)
- [框架集成示例](#框架集成示例)
- [网关引擎](#网关引擎)
- [监控与告警](#监控与告警)
- [配置参考](#配置参考)
- [文档](#文档)
- [系统要求](#系统要求)
- [License](#license)

---

## 功能清单

### 内核

| 功能 | 说明 |
|------|------|
| SDK 接口 | 6 个标准接口（Protocol / Connector / Driver / Frame / DataPoint / GatewayRule），第三方可基于接口开发新协议包 |
| 协议注册 | ProtocolRegistry 自动扫描 Composer 安装的协议包，零配置加载 |
| 连接管理 | 3 种策略 — Lazy（按需连接）、Eager（启动即连）、Pooled（连接池），支持健康检查、自动重连 |
| 配置管理 | 3 种实现 — FileConfigRepository（PHP 文件）、DatabaseConfigRepository（PDO/SQLite/MySQL）、EnvConfigRepository |
| 协程适配 | Swoole → Fiber → Sync 三级自动降级，框架选择最佳的协程运行时 |
| 事件系统 | 13 个事件类型，基于 PSR-14 EventDispatcher，支持自定义监听器 |
| 日志驱动 | 3 种实现 — PsrLogDriver（委托 PSR-3）、FileLogDriver（直接写文件）、NullLogDriver（关闭日志） |
| 重试策略 | 4 种策略 — NoRetry、FixedRetry、ExponentialBackoff、ExponentialBackoff + Jitter |
| 异常体系 | 20+ 分层异常：Connection / Protocol / Device / Gateway，附带上下文信息 |
| 框架适配 | 6 个框架 + 纯 PHP，安装即用，内核自动检测运行环境 |

### 网关引擎

| 功能 | 说明 |
|------|------|
| 规则引擎 | 支持 poll（定时轮询）、change（变化触发）、cron（定时表达式）三种触发模式 |
| 数据管道 | Source Frame → Parse → Transform → Encode → Target Frame，支持自定义转换函数 |
| 熔断器 | CLOSED → OPEN → HALF_OPEN 状态机，防止级联故障，可配置阈值和冷却时间 |
| 并发执行 | 协程环境下多规则并行执行，FPM 环境顺序执行 |

### 监控与安全

| 功能 | 说明 |
|------|------|
| 指标采集 | Counter / Gauge / Histogram，支持 Prometheus 文本格式导出 |
| 告警通道 | AlertManager + Webhook / Log 通道，多通道同时推送 |
| 输入校验 | InputValidator：设备 ID、主机地址、端口号、寄存器地址、帧大小、超时范围 |

---

## 支持的工业通信协议

| 协议 | 阶段 | 变体 | 默认端口 | 实现方式 | 支持操作 |
|------|------|------|---------|---------|----------|
| **Modbus** | Phase 1 | TCP, RTU, ASCII | 502 | 纯 PHP Socket | FC 01/03/04/06/10（读写线圈/保持寄存器/输入寄存器） |
| **BACnet/IP** | Phase 3 | IP (UDP) | 47808 | 纯 PHP UDP Socket | Who-Is/I-Am 设备发现、ReadProperty |
| **EtherNet/IP** | Phase 3 | TCP | 44818 | 纯 PHP Socket | ENIP 会话管理、CIP Read Tag |
| **OPC UA** | 规划中 | Binary | 4840 | FFI / C 桥接 | — |
| **Profinet** | 规划中 | NRT + RT | 34964 | FFI / C 库桥接 | — |

---

## 支持的框架

| 框架 | 阶段 | 检测方式 | 协程支持 | 集成方式 |
|------|------|---------|---------|----------|
| **Plain PHP** | Phase 1 | 默认回退 | Fiber (PHP 8.1+) | 直接实例化 Kernel |
| **Laravel** | Phase 2 | `Illuminate\Foundation\Application` | Laravel Octane (Swoole) | ServiceProvider + Facade + artisan 命令 |
| **Webman** | Phase 2 | `Workerman\Worker` | Swoole Event Driver / Fiber | `config/plugin` 自动发现 + ProtocolProcess |
| **Hyperf** | Phase 3 | `Hyperf\Framework\ApplicationFactory` | Swoole 原生 | ConfigProvider + DI 容器深度整合 |
| **ThinkPHP** | Phase 3 | `think\App` | think-swoole | services.php 自动发现 + 单例服务 |
| **Yii2** | Phase 3 | `yii\base\Application` | swoole-yii2 | Bootstrap + 应用组件注册 |

框架检测优先级：`Laravel → Webman → Hyperf → ThinkPHP → Yii2 → Plain PHP`

---

## 快速开始

### 安装

```bash
composer require industrial-protocols/kernel industrial-protocols/modbus
```

### 5 分钟上手

```php
<?php
require 'vendor/autoload.php';

use IndustrialProtocols\Kernel;
use IndustrialProtocols\Modbus\ModbusProtocol;

// 1. 创建配置
$config = __DIR__ . '/industrial-protocols.php';
file_put_contents($config, '<?php return ' . var_export([
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'variant'  => 'tcp',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
            'timeout'  => 3000,
        ],
    ],
    'gateway'      => ['rules' => []],
    'health_check_interval' => 30,
], true) . ';');

// 2. 启动内核
$kernel = new Kernel(['config_path' => $config]);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

// 3. 连接设备并读取数据
$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
echo "温度: " . $result['40001'] . "\n";

// 4. 写入数据
$conn->write(['40001' => 25]);

// 5. 健康检查
$health = $kernel->getConnectionManager()->health('plc-001');
echo "状态: " . $health->state->value . ", 延迟: " . $health->latencyMs . "ms\n";

$kernel->shutdown();
```

---

## 使用说明

### Kernel 生命周期

```
实例化 Kernel → register protocols → boot() → [使用] → shutdown()
```

- **实例化**后 protocol registry 即可用，注册协议包
- **boot()** 完成框架检测、配置加载、连接管理器初始化
- **shutdown()** 关闭所有设备连接

### ConnectionManager API

```php
$manager = $kernel->getConnectionManager();

// 连接设备（根据 strategy 决定连接时机）
$conn = $manager->connect('plc-001');

// 获取已有连接（不会触发连接建立）
$existing = $manager->getConnection('plc-001');

// 断开连接
$manager->disconnect('plc-001');

// 获取所有活跃连接
$all = $manager->getAllConnections();

// 单设备健康检查
$health = $manager->health('plc-001');

// 全部设备健康检查
$allHealth = $manager->healthAll();
```

### 连接策略对比

```php
// LAZY（默认）— FPM 环境推荐，首次读写时才连接
$kernel = new Kernel([
    'config_path' => $config,
]);

// EAGER — 常驻进程推荐，启动时建立所有连接
use IndustrialProtocols\Connection\Strategy\EagerStrategy;

// POOLED — 高频轮询/网关场景，预建连接池
use IndustrialProtocols\Connection\Strategy\PooledStrategy;
// poolSize=4 时，getOrCreate 以轮询方式从池中分配连接
```

### 重试配置

```php
// 配置文件
'default_retry_max'     => 3,
'default_retry_backoff' => 'exponential',  // exponential | fixed | none

// 程序化配置
use IndustrialProtocols\Retry\ExponentialBackoffStrategy;
use IndustrialProtocols\Exception\ConnectionTimeoutException;

$strategy = new ExponentialBackoffStrategy(
    maxAttempts: 5,
    baseDelayMs: 1000,
    jitter: true,                                        // 随机抖动防雷群
    retryableExceptions: [ConnectionTimeoutException::class],  // 仅此类异常触发重试
);
```

### 事件监听

```php
use IndustrialProtocols\Event\DataReadEvent;
use IndustrialProtocols\Event\ConnectionStateChangedEvent;

$dispatcher->listen(DataReadEvent::class, function (DataReadEvent $e) {
    echo "设备 {$e->deviceId} 读操作完成，延迟 {$e->latencyMs}ms\n";
});

$dispatcher->listen(ConnectionStateChangedEvent::class, function ($e) {
    if ($e->newStatus->state->value === 'FAULT') {
        // 触发告警
    }
});
```

---

## 协议使用示例

### Modbus TCP

```php
use IndustrialProtocols\Modbus\ModbusProtocol;

$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('plc-001');

// 读取保持寄存器
$result = $conn->read('40001');           // 单寄存器 → ['40001' => 237]
$batch  = $conn->read(['40001', '40002']); // 批量读取

// 写入保持寄存器
$conn->write(['40001' => 100]);
$conn->write(['40001' => 200, '40002' => 300]);

// 地址格式
// 40001-49999  保持寄存器（Read/Write）
// 30001-39999  输入寄存器（Read Only）
// 0-9999       原始偏移
```

### BACnet/IP

```php
use IndustrialProtocols\Bacnet\BacnetProtocol;

$kernel->getProtocolRegistry()->register(new BacnetProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('bacnet-device');

// 发现网络中的 BACnet 设备（Who-Is 广播）
$devices = $conn->discoverDevices(5);  // 5 秒超时

// 读取属性: ObjectType:Instance:PropertyId
$result = $conn->read('0:1:85');       // AnalogInput 1, PresentValue
```

### EtherNet/IP

```php
use IndustrialProtocols\EtherNetIP\EtherNetIPProtocol;

$kernel->getProtocolRegistry()->register(new EtherNetIPProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('eip-plc');

// 读取 CIP 标签
$result = $conn->read('MyTagName');
```

---

## 框架集成示例

### Laravel

```bash
# 发布配置文件
php artisan vendor:publish --tag=industrial-protocols-config
```

```php
// app/Providers/AppServiceProvider.php
use IndustrialProtocols\Kernel;
use IndustrialProtocols\Modbus\ModbusProtocol;

public function boot(): void
{
    $kernel = app(Kernel::class);
    $kernel->getProtocolRegistry()->register(new ModbusProtocol());
}

// 使用 Facade
use IndustrialProtocols\Framework\Laravel\IndustrialProtocolsFacade;

$result = IndustrialProtocolsFacade::connect('plc-001')->read('40001');
```

```bash
# Artisan 命令
php artisan industrial:connect plc-001
php artisan industrial:gateway:list
```

### Webman

Webman 通过 `config/plugin/` 目录自动发现插件，安装即用。

创建配置文件：

```php
// config/plugin/industrial-protocols/kernel/config/industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'variant'  => 'tcp',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
            'timeout'  => 3000,
        ],
    ],
    // ...
];
```

ProtocolProcess 在 Worker 启动时自动初始化 Kernel、注册协议包、建立连接。无需额外代码。

### Hyperf

配置自动通过 ConfigProvider 注入。创建配置文件：

```php
// config/autoload/industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
        ],
    ],
];
```

```php
// 控制器中使用
use Hyperf\Context\ApplicationContext;
use IndustrialProtocols\Kernel;

$kernel = ApplicationContext::getContainer()->get(Kernel::class);
$conn = $kernel->getConnectionManager()->connect('plc-001');
$result = $conn->read('40001');
```

```bash
# Hyperf 命令
php bin/hyperf.php industrial:connect plc-001
php bin/hyperf.php industrial:gateway:list
```

### ThinkPHP

```php
// app/service.php 中添加
use IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;

// 任意位置使用
use IndustrialProtocols\Framework\ThinkPHP\IndustrialProtocolsService;

$kernel = IndustrialProtocolsService::boot();
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

### Yii2

```php
// config/web.php
return [
    'bootstrap' => [
        'industrial-protocols' => \IndustrialProtocols\Framework\Yii2\Bootstrap::class,
    ],
    'components' => [
        'industrial-protocols' => [
            'class' => \IndustrialProtocols\Kernel::class,
        ],
    ],
];
```

```php
// 控制器中使用
$kernel = Yii::$app->get('industrial-protocols');
$conn = $kernel->getConnectionManager()->connect('plc-001');
```

---

## 网关引擎

实现跨协议数据转发（如 Modbus → OPC UA）：

```php
use IndustrialProtocols\Gateway\GatewayEngine;
use IndustrialProtocols\Gateway\GatewayRule;

$engine = new GatewayEngine(
    $kernel->getConnectionManager(),
    $eventDispatcher,
    $kernel->getCoroutineAdapter(),
    $kernel->getLogDriver(),
);

// 规则：每 1000ms 读取 plc-001 的 40001，写入 opcua-server
$engine->addRule(new GatewayRule(
    id: 'modbus-to-opcua',
    sourceDevice: 'plc-001',
    sourcePoint:  '40001',
    targetDevice: 'opcua-server',
    targetPoint:  'ns=1;s=Temperature',
    transform:    fn($raw) => $raw / 10,  // 原始值除以 10
    trigger:      'poll',
    interval:     1000,                     // ms
));

// 执行一次
$result = $engine->executeOnce('modbus-to-opcua');

// 或持续运行（协程环境下多条规则并发执行）
$engine->run(tickIntervalMs: 100);
$engine->stop();
```

### 触发模式

| 模式 | 行为 | 适用场景 |
|------|------|---------|
| `poll` | 每隔 N ms 拉取源数据写入目标 | 持续采集显示 |
| `change` | 仅源数据变化时写入 | 报警、事件通知 |
| `cron` | 按 cron 表达式批量同步 | 定时报表 |

### 熔断器

单条规则连续失败 N 次后自动熔断，冷却时间到后半开试探：

```php
new GatewayRule(
    // ...
    failureThreshold: 5,      // 连续 5 次失败触发熔断
    cooldownSeconds: 30.0,    // 30 秒后尝试恢复
)
```

---

## 监控与告警

### 指标采集

```php
use IndustrialProtocols\Metrics\MetricsCollector;

$metrics = new MetricsCollector();

// 计数器 — 累计读写次数
$metrics->incrementCounter('reads_total', ['device' => 'plc-001']);
$metrics->incrementCounter('writes_total', ['device' => 'plc-001'], 5);

// 仪表盘 — 活跃连接数
$metrics->setGauge('active_connections', count($manager->getAllConnections()));

// 直方图 — 读取延迟分布
$metrics->observeHistogram('read_latency_ms', 15.2, ['device' => 'plc-001']);

// Prometheus 格式导出
header('Content-Type: text/plain');
echo $metrics->toPrometheus('industrial');
```

### 告警推送

```php
use IndustrialProtocols\Alert\AlertManager;
use IndustrialProtocols\Alert\WebhookAlertChannel;

$alert = new AlertManager();
$alert->addChannel('dingtalk', new WebhookAlertChannel('https://oapi.dingtalk.com/robot/send?...'));
$alert->addChannel('feishu',   new WebhookAlertChannel('https://open.feishu.cn/open-apis/bot/v2/hook/...'));

// 连接故障时推送
$alert->send('设备断连', 'plc-001 连接超时', level: 'critical');
```

---

## 配置参考

```php
<?php
// industrial-protocols.php
return [
    // 设备连接配置
    'devices' => [
        'plc-001' => [
            'protocol'  => 'modbus',        // 协议名称
            'variant'   => 'tcp',           // 协议变体
            'host'      => '192.168.1.10',  // 设备 IP 或串口
            'port'      => 502,             // 端口
            'unit_id'   => 1,               // 从站 ID
            'timeout'   => 3000,            // 超时 (ms)
            'strategy'  => 'lazy',          // 连接策略: lazy | eager | pooled
            'pool_size' => 4,               // 连接池大小（pooled 策略）
            'points'    => [                // 数据点位映射
                ['address' => '40001', 'name' => 'temperature', 'type' => 'FLOAT32', 'access' => 'RW'],
                ['address' => '40003', 'name' => 'pressure',    'type' => 'FLOAT32', 'access' => 'RO'],
            ],
        ],
    ],

    // 网关规则
    'gateway' => [
        'rules' => [
            [
                'id'             => 'gw-001',
                'source_device'  => 'plc-001',
                'source_point'   => '40001',
                'target_device'  => 'opcua-server',
                'target_point'   => 'ns=1;s=Temperature',
                'trigger'        => 'poll',    // poll | change | cron
                'interval'       => 1000,
            ],
        ],
    ],

    // 全局默认值
    'health_check_interval' => 30,          // 健康检查间隔 (s)
    'default_retry_max'     => 3,           // 最大重试次数
    'default_retry_backoff' => 'exponential', // 退避策略
    'default_timeout'       => 3000,        // 默认超时 (ms)
];
```

---

## 文档

- [协议 API 参考](docs/protocols.md) — Modbus、BACnet、EtherNet/IP 连接配置、读写操作、地址格式
- [框架集成指南](docs/framework-integration.md) — Plain PHP、Laravel、Webman、Hyperf、ThinkPHP、Yii2 集成详述
- [网关引擎指南](docs/gateway.md) — 规则配置、触发模式、熔断器、数据变换管道
- [安全指南](docs/security.md) — 输入校验、网络安全、异常参考

---

## 系统要求

- PHP >= 8.1
- Composer
- 可选：ext-swoole（Swoole 协程加速）
- 可选：ext-pdo（数据库配置存储）

---

## License

MIT
