# Protocol API Reference

> [中文](../protocols.md)

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

## OPC UA Binary

### Connection Configuration

```php
'devices' => [
    'opcua-server' => [
        'protocol'        => 'opc-ua',
        'variant'         => 'binary',
        'host'            => '192.168.1.100',
        'port'            => 4840,
        'timeout'         => 5000,
        'application_uri' => 'urn:myapp:industrial-protocols',
        'session_name'    => 'PHP-OPCUA-Client',
    ],
]
```

### Reading Node Values

```php
$conn = $manager->connect('opcua-server');

// Read nodes (supports multiple address formats)
$result = $conn->read('ns=0;i=2258');        // CurrentTime
$result = $conn->read('i=2258');              // defaults to ns=0
$result = $conn->read('ns=2;s=Temperature');  // string identifier

// Batch read
$result = $conn->read(['ns=0;i=2258', 'ns=2;s=Temperature']);
```

### Writing Node Values

```php
$conn->write(['ns=2;s=SetPoint' => 100.0]);
```

### Browsing the Address Space

```php
$children = $conn->browse('i=85'); // Browse nodes under the Objects folder
```

### Address Formats

| Format | Example | Description |
|--------|---------|-------------|
| `ns=N;i=N` | `ns=0;i=2258` | Numeric identifier with namespace |
| `i=N` | `i=2258` | Numeric identifier without namespace (ns=0) |
| `ns=N;s=X` | `ns=2;s=Temperature` | String identifier with namespace |
| `s=X` | `s=MyVar` | String identifier without namespace |

## Profinet NRT

> **Note:** Profinet is divided into RT (real-time) and NRT (non-real-time) channels. The RT channel requires dedicated ERTEC hardware chips and cannot be implemented in PHP. This package implements the NRT channel: DCP device discovery, Record Data read/write, and diagnostics.

### Connection Configuration

```php
'devices' => [
    'pn-device' => [
        'protocol'  => 'profinet',
        'variant'   => 'nrt',
        'host'      => '192.168.1.30',
        'port'      => 34964,
        'transport' => 'udp',     // UDP (DCP) or TCP (Record Data)
        'timeout'   => 5000,
    ],
]
```

### Device Discovery (DCP)

```php
$conn = $manager->connect('pn-device');
$devices = $conn->discoverDevices(5); // DCP Identify broadcast
// Returns: [['name' => 'pn-device-1', 'ip' => '192.168.1.30'], ...]
```

### Reading Record Data

```php
// Address format: api:slot:subslot:index
$result = $conn->read('0:0:1:0xAFF0');  // Read module diagnostic data
$result = $conn->read('0:1:1:0x0001');  // Read module parameters
```

### Writing Record Data

```php
$conn->write(['0:0:1:0x0100' => 0x0001]); // Write parameter
```

## Modbus RTU

### Connection Configuration

```php
'devices' => [
    'plc-rtu' => [
        'protocol'  => 'modbus',
        'variant'   => 'rtu',
        'device'    => '/dev/ttyUSB0',  // Linux serial port
        'baud_rate' => 19200,
        'parity'    => 'N',             // N | E | O
        'data_bits' => 8,
        'stop_bits' => 1,
        'unit_id'   => 1,
        'timeout'   => 3000,
    ],
]
```

## HART

### Connection Configuration

```php
'devices' => [
    'hart-device' => [
        'protocol' => 'hart',
        'device'   => '/dev/ttyUSB1',  // HART modem
        'address'  => 0,               // polling address: 0=single, 1-15=multi-drop
        'timeout'  => 5000,
    ],
]
```

### Reading

```php
$conn->read('pv');            // Primary Variable
$conn->read('loop_current');   // Loop current (mA)
$conn->read('device_info');    // Device info (vendor/model/revision)
```

## CC-Link

### Connection Configuration

```php
'devices' => [
    'cclink-device' => [
        'protocol'  => 'cc-link',
        'variant'   => 'rs485',
        'device'    => '/dev/ttyUSB2',
        'baud_rate' => 156000,
        'station'   => 0,  // master=0
        'timeout'   => 3000,
    ],
]
```

## Fieldbus Bridge Protocols

The following fieldbus protocols are adapted through the Bridge layer:

| Protocol | Hardware Required | Vendor Solutions |
|----------|------------------|-----------------|
| PROFIBUS DP/PA | RS-485 interface card | Siemens CP 5611, Anybus, Hilscher cifX |
| CANopen | CAN interface | PCAN-USB, IXXAT, SocketCAN |
| DeviceNet | CAN interface | Anybus DeviceNet Scanner |
| Foundation Fieldbus | FF H1 interface | NI USB-8486, Softing FFusb |
| AS-Interface | AS-i Master | Bihl+Wiedemann, Pepperl+Fuchs |
| IO-Link | IO-Link Master | ifm AL1330, Balluff |
| CC-Link IE | Ethernet gateway | CC-Link IE Field gateway |

## Hardware Bridge Protocols (EtherCAT / POWERLINK / SERCOS III / Profinet RT / TSN)

The following protocols require dedicated hardware chips or real-time kernels, making direct PHP protocol stack implementation infeasible. This library adapts vendor C/C++ SDKs or gateway hardware through a **Bridge layer**.

### Bridge Types

| Bridge | Description | Use Case |
|--------|-------------|----------|
| `ExternalProcessBridge` | Launches a C/C++ SDK subprocess, communicates via stdin/stdout | Vendor provides command-line SDK tools |
| `TcpGatewayBridge` | TCP/UDP connection to gateway hardware | Anybus / Hilscher / custom gateway |

For detailed vendor configurations, device models, and SDK paths, see the [Vendor Adapters Reference](vendors.md).

### Bridge Configuration Example

```php
use IndustrialProtocols\Bridge\ExternalProcessBridge;
use IndustrialProtocols\Bridge\TcpGatewayBridge;
use IndustrialProtocols\EtherCat\EtherCatProtocol;

// Method 1: Via C/C++ SDK subprocess
$bridge = new ExternalProcessBridge('/opt/ethercat-sdk/bin/ecat_master');
$kernel->getProtocolRegistry()->register(new EtherCatProtocol());
$kernel->boot();

$conn = $kernel->getConnectionManager()->connect('ethercat-device', [
    'protocol' => 'ethercat',
    'bridge'   => $bridge,
]);
$result = $conn->read('0x6000:0x01'); // CoE SDO read

// Method 2: Via gateway hardware
$bridge = new TcpGatewayBridge('192.168.1.200', 5555);
$conn = $kernel->getConnectionManager()->connect('powerlink-device', [
    'protocol' => 'powerlink',
    'bridge'   => $bridge,
]);
```

### Supported Bridge Protocols

| Protocol | Hardware/SDK Required | Bridge Type |
|----------|----------------------|-------------|
| EtherCAT | Beckhoff TwinCAT SDK / SOEM (Simple Open EtherCAT Master) | ExternalProcessBridge |
| POWERLINK | openPOWERLINK stack / B&R Automation Studio | ExternalProcessBridge |
| SERCOS III | Bosch Rexroth SERCOS IP core / Hilscher netX | TcpGatewayBridge |
| Profinet RT/IRT | Siemens ERTEC / Hilscher netX | TcpGatewayBridge |
| TSN | TSN NIC (Intel I225-T1 / NXP SJA1110) + 802.1Qbv driver | ExternalProcessBridge |

## Connection Management

### ConnectionManager API

```php
$manager = $kernel->getConnectionManager();

// Connect (lazy strategy by default)
$conn = $manager->connect('plc-001');

// Reuse existing connection
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

PSR-14 events dispatched by the kernel:

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
