# Protocol API Reference

## Modbus TCP/RTU

### Connection Configuration

```php
'devices' => [
    'plc-001' => [
        'protocol' => 'modbus',
        'variant'  => 'tcp',        // tcp | rtu | ascii
        'host'     => '192.168.1.10',
        'port'     => 502,
        'unit_id'  => 1,
        'timeout'  => 3000,          // ms
    ],
]
```

### Reading Registers

```php
$conn = $manager->connect('plc-001');

// Single register
$result = $conn->read('40001');         // ['40001' => 42]

// Multiple registers
$result = $conn->read(['40001', '40002']);  // ['40001' => 42, '40002' => 100]
```

### Writing Registers

```php
$conn->write('40001', [100]);                         // single register
$conn->write(['40001', '40002'], [200, 300]);          // multiple indexed
$conn->write(['40001', '40002'], ['40001' => 200, '40002' => 300]);  // multiple keyed
```

### Address Formats

| Range | Type | Offset |
|-------|------|--------|
| 40001-49999 | Holding Register | addr - 40001 |
| 30001-39999 | Input Register | addr - 30001 |
| 0-9999 | Raw offset | direct |

### Health Check

```php
$health = $manager->health('plc-001');
echo $health->state->value;    // HEALTHY | CLOSED | FAILED
echo $health->latencyMs;       // round-trip latency
echo $health->lastError;       // error message, if any
```

## BACnet/IP

### Connection Configuration

```php
'devices' => [
    'bacnet-device' => [
        'protocol'  => 'bacnet',
        'variant'   => 'ip',
        'host'      => '192.168.1.50',
        'port'      => 47808,
        'device_id' => 1234,
        'timeout'   => 3000,
    ],
]
```

### Device Discovery

```php
$conn = $manager->connect('bacnet-device');
$devices = $conn->discoverDevices(5); // 5 second timeout
```

### Reading Properties

```php
// ObjectType:ObjectInstance:PropertyId
$result = $conn->read('0:1:85');  // AnalogInput 1, PresentValue
$result = $conn->read('0:2:85');  // AnalogInput 2, PresentValue
```

If PropertyId is omitted, PresentValue (85) is the default.

## EtherNet/IP

### Connection Configuration

```php
'devices' => [
    'eip-plc' => [
        'protocol' => 'ethernet-ip',
        'variant'  => 'tcp',
        'host'     => '192.168.1.20',
        'port'     => 44818,
        'timeout'  => 3000,
    ],
]
```

### Reading Tags

```php
$conn = $manager->connect('eip-plc');
$result = $conn->read('MyTag');         // ['MyTag' => <value>]
$result = $conn->read(['Tag1', 'Tag2']); // ['Tag1' => <v1>, 'Tag2' => <v2>]
```

The EtherNet/IP connector registers a CIP session on connect and unregisters it on disconnect.

## Connection Management

### ConnectionManager API

```php
$manager = $kernel->getConnectionManager();

// Connect to a device (lazy strategy by default)
$conn = $manager->connect('plc-001');

// Reuse an existing connection
$conn = $manager->getConnection('plc-001');

// Disconnect
$manager->disconnect('plc-001');

// Health check for a single device
$health = $manager->health('plc-001');

// Health check for all connected devices
$allHealth = $manager->healthAll();

// List all active connections
$connections = $manager->getAllConnections();

// Shut down all connections
$manager->shutdown();
```

### Connection Strategies

| Strategy | Behavior |
|----------|----------|
| `LazyStrategy` (default) | Connects on first use, caches per device ID |
| `EagerStrategy` | Connects all configured devices at boot |
| `PooledStrategy` | Maintains a connection pool per device |

### Events

The kernel dispatches PSR-14 events for key lifecycle points:

| Event | Fires When |
|-------|------------|
| `KernelBootedEvent` | Kernel completes boot |
| `ConnectionConnectedEvent` | Device connection established |
| `ConnectionDisconnectedEvent` | Device connection closed |
| `ConnectionStateChangedEvent` | Connection state transitions |
| `ConnectionRetryEvent` | Connection retry attempted |
| `DataReadEvent` | Data read from a device |
| `DataWriteEvent` | Data written to a device |
| `DataErrorEvent` | Error during data operation |
