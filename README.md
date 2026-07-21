# Industrial Protocols PHP

PHP industrial network communication protocol plugin -- micro-kernel architecture with
protocol SDK, supporting Modbus, OPC UA, Profinet, EtherNet/IP, BACnet and more.

## Supported Protocols

| Protocol | Status | Variants |
|----------|--------|----------|
| Modbus      | Phase 1 | TCP, RTU, ASCII |
| BACnet      | Phase 3 | IP (UDP)        |
| EtherNet/IP | Phase 3 | TCP             |
| OPC UA      | Planned | Binary          |
| Profinet    | Planned | NRT + RT        |

## Supported Frameworks

| Framework | Status |
|-----------|--------|
| Plain PHP | Phase 1 |
| Laravel   | Phase 2 |
| Webman    | Phase 2 |
| Hyperf    | Phase 3 |
| ThinkPHP  | Phase 3 |
| Yii2      | Phase 3 |

## Quick Start

```bash
composer require industrial-protocols/kernel industrial-protocols/modbus
```

```php
<?php
require 'vendor/autoload.php';

use IndustrialProtocols\Kernel;
use IndustrialProtocols\Modbus\ModbusProtocol;

$kernel = new Kernel(['config_path' => __DIR__ . '/industrial-protocols.php']);
$kernel->getProtocolRegistry()->register(new ModbusProtocol());
$kernel->boot();

// Connect to a Modbus TCP device
$conn = $kernel->getConnectionManager()->connect('plc-001');

// Read holding register 40001
$result = $conn->read('40001');
echo $result['40001']; // e.g. 23.5

// Write to holding register 40001
$conn->write(['40001' => 100]);

// Health check
$health = $kernel->getConnectionManager()->health('plc-001');
echo $health->state->value; // HEALTHY

$kernel->shutdown();
```

## Gateway Engine (Phase 2)

Forward data between devices using different protocols:

```php
use IndustrialProtocols\Gateway\GatewayRule;

$kernel->boot();

// Add a gateway rule: poll Modbus register and forward to OPC UA
$engine = new \IndustrialProtocols\Gateway\GatewayEngine(
    $kernel->getConnectionManager(),
    /* eventDispatcher, coroutineAdapter, logDriver */
);

$engine->addRule(new GatewayRule(
    id: 'modbus-to-opcua',
    sourceDevice: 'plc-001',
    sourcePoint: '40001',
    targetDevice: 'opcua-server',
    targetPoint: 'ns=1;s=Temperature',
    transform: fn($v) => $v / 10, // scale raw value
    trigger: 'poll',
    interval: 1000,
));

// Execute once on demand
$result = $engine->executeOnce('modbus-to-opcua');

// Or run continuous poll loop
$engine->run(tickIntervalMs: 1000);
```

## Laravel Integration (Phase 2)

```bash
php artisan vendor:publish --tag=industrial-protocols-config
```

```php
// config/industrial-protocols.php
// Register protocols in AppServiceProvider:
use IndustrialProtocols\Modbus\ModbusProtocol;

app(Kernel::class)->getProtocolRegistry()->register(new ModbusProtocol());
app(Kernel::class)->boot();

// Use the facade
\IndustrialProtocols\Facades\IndustrialProtocols::connect('plc-001')->read('40001');
```

## Webman Integration (Phase 2)

Webman auto-discovers the plugin via `config/plugin/`. Install and configure -- the ProtocolProcess auto-boots on worker start.

## Configuration

```php
<?php
// industrial-protocols.php
return [
    'devices' => [
        'plc-001' => [
            'protocol' => 'modbus',
            'variant'  => 'tcp',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
            'timeout'  => 3000,
            'points'   => [
                ['address' => '40001', 'name' => 'temperature', 'type' => 'FLOAT32', 'access' => 'RW'],
            ],
        ],
    ],
    'gateway' => ['rules' => []],
    'health_check_interval' => 30,
    'default_retry_max' => 3,
    'default_retry_backoff' => 'exponential',
];
```

## Requirements

- PHP >= 8.1
- ext-sockets (for Modbus TCP)
- Composer

## License

MIT
