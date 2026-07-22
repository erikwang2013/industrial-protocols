# Industrial Protocols Kernel — 微内核

> [English](README.en.md)

微内核 — 提供连接管理、配置管理、网关引擎、事件系统、协程适配、框架适配、硬件桥接、厂商配置等基础设施。

## 安装

```bash
composer require erikwang2013/industrial-protocols-kernel
```

## 使用

```php
use Erikwang2013\IndustrialProtocols\Kernel;
$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->boot();
$conn = $kernel->getConnectionManager()->connect('device-id');
```

## 功能

协议注册(ProtocolRegistry,composer extra 自动发现)、连接管理(Lazy/Eager/Pooled 三种策略)、配置管理(File/Database/Env 三种实现)、网关引擎(GatewayEngine,poll/change/cron 触发)、协程适配(Swoole→Fiber→Sync 三级降级)、框架适配(Laravel/Webman/Hyperf/ThinkPHP/Yii2/PlainPHP)、硬件桥接(BridgeInterface/ExternalProcessBridge/TcpGatewayBridge)、厂商配置(12 家预置)、事件系统(PSR-14,13 种事件)、日志(PSR-3/File/Null)、重试(NoRetry/Fixed/ExponentialBackoff+Jitter)、指标(Prometheus 导出)、告警(Webhook/Log 通道)、安全(InputValidator 校验)、异常(20+ 种分层异常)

## 系统要求

- PHP >= 8.1
- Composer

## License

MIT — Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz


---

## 相关链接

- [Industrial Protocols 主项目](https://github.com/erikwang2013/industrial-protocols)
- [Kernel 内核](https://github.com/erikwang2013/industrial-protocols-kernel)
- [全部 42 个协议包](https://github.com/erikwang2013/industrial-protocols#支持的协议)

