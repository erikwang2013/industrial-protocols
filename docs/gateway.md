# 网关引擎指南

> [English](en/gateway.md)

网关引擎用于在工业设备之间转发数据，支持跨协议转换、数值变换和多种触发模式。是内核 Phase 2 的核心组件。

## 配置方式

```php
'gateway' => [
    'rules' => [
        [
            'id'             => 'modbus-to-opcua',
            'source_device'  => 'plc-001',
            'source_point'   => '40001',
            'target_device'  => 'opcua-server',
            'target_point'   => 'ns=1;s=Temperature',
            'transform'      => null,       // 可选的数值转换回调
            'trigger'        => 'poll',     // poll | change | cron
            'interval'       => 1000,       // 毫秒
        ],
    ],
],
```

## GatewayRule 构造函数

```php
use Erikwang2013\IndustrialProtocols\Gateway\GatewayRule;

$rule = new GatewayRule(
    id: 'modbus-to-opcua',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'opcua-server',
    targetPoint: 'ns=1;s=Temperature',
    transform: fn($v) => $v / 10,     // 可选的数值转换
    trigger: 'poll',                   // 'poll' | 'change' | 'cron'
    interval: 1000,                    // 毫秒
    cronExpression: null,              // cron 语法（cron 触发模式用）
    failureThreshold: 5,               // 熔断阈值（连续失败次数）
    cooldownSeconds: 30.0,             // 熔断冷却时间（秒）
);
```

所有构造函数参数均为 `readonly` 公开属性。

## 触发模式

| 模式 | 行为 |
|------|----------|
| `poll` | 每隔 N 毫秒读取源数据并写入目标（无条件写入） |
| `change` | 仅当源数据值相比上次读取发生变化时才写入目标 |
| `cron` | 按 cron 表达式定时批量同步（需设置 `cronExpression`） |

`change` 模式下，引擎记录每条规则上次读取的值，值与上次相同时跳过写入。

## 引擎生命周期

### 实例化

```php
use Erikwang2013\IndustrialProtocols\Gateway\GatewayEngine;

$engine = new GatewayEngine(
    $kernel->getConnectionManager(),
    $eventDispatcher,
    $kernel->getCoroutineAdapter(),
    $kernel->getLogDriver(),
);
```

### 添加和移除规则

```php
$engine->addRule(new GatewayRule(
    id: 'temp-fwd',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'scada',
    targetPoint: 'AI1',
    trigger: 'poll',
    interval: 2000,
));

$engine->removeRule('temp-fwd');
```

### 执行模式

```php
// 按需执行单条规则
$result = $engine->executeOnce('temp-fwd');
// 返回：['status' => 'ok', 'rule' => 'temp-fwd', 'value' => 42, 'latency_ms' => 3.2]

// 批量执行所有 poll 规则
$results = $engine->tick();
// 返回按规则 ID 索引的结果数组

// 持续循环运行
$engine->run(tickIntervalMs: 100);
```

### 停止引擎

```php
$engine->stop();  // 终止 run() 循环
```

## 数值变换管道

每条规则按以下管道执行：

```
读取源帧 → 解析 → 提取值 → 变换回调 → 构造目标帧 → 写入目标
```

### 变换示例

```php
// 摄氏度转华氏度
'transform' => fn($celsius) => $celsius * 9 / 5 + 32,

// 原始整数缩放为小数
'transform' => fn($raw) => $raw / 10,

// 限幅
'transform' => fn($v) => min(max($v, 0), 100),

// 格式化字符串
'transform' => fn($v) => number_format($v, 2),
```

`transform` 设为 `null` 时直接透传原始值。

## 熔断器

每条规则自动关联一个熔断器，防止设备不可达时产生级联故障。

### 状态

| 状态 | 含义 |
|-------|---------|
| `CLOSED`（闭合）| 正常运行，请求放行 |
| `OPEN`（断开）| 失败次数超过阈值，请求被拦截 |
| `HALF_OPEN`（半开）| 冷却时间到，下次请求试探恢复 |

### 默认参数

- **熔断阈值**：连续 5 次失败
- **冷却时间**：30 秒
- 冷却后进入 HALF_OPEN 状态；下次执行成功则恢复到 CLOSED，失败则重新 OPEN

### 事件

| 事件 | 触发时机 |
|-------|------------|
| `GatewayRuleStartedEvent` | 规则开始执行 |
| `GatewayRuleCompletedEvent` | 规则执行成功（含延迟数据） |
| `GatewayRuleFailedEvent` | 规则执行失败（含失败计数） |
| `GatewayCircuitBreakerEvent` | 熔断器状态变化 |

### 监控

重复的熔断器跳闸表明设备存在持续性问题：

```php
$eventDispatcher->addListener(
    Erikwang2013\IndustrialProtocols\Event\GatewayCircuitBreakerEvent::class,
    function ($event) {
        // 记录日志、发送告警或通知
    }
);
```

## 协程并发

在支持协程的环境（Swoole、Fiber）中，`tick()` 调用时所有 poll 规则并发执行。同步环境则顺序执行。

## 使用案例

### 将 Modbus 寄存器转发到 BACnet

```php
$engine->addRule(new GatewayRule(
    id: 'modbus-to-bacnet',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'bacnet-scada',
    targetPoint: '0:1:85',
    transform: fn($v) => (int)($v * 100),
    trigger: 'change',       // 仅在值变化时转发
));
```

### 定时数据记录

```php
$engine->addRule(new GatewayRule(
    id: 'logger',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'logger-db',
    targetPoint: 'temperature',
    trigger: 'poll',
    interval: 60000,  // 每 60 秒
));
```
