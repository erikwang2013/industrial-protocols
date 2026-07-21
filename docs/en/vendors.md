# Hardware Vendor Adapter Reference

> [Chinese version](../vendors.md)

This library adapts C/C++ SDKs and gateway devices from major industrial hardware vendors through the Bridge layer.

## Vendor Overview

| Vendor | Protocol | Bridge Type | SDK/Interface |
|------|------|-----------|---------|
| Beckhoff | EtherCAT | ExternalProcessBridge | TwinCAT ADS |
| Siemens | PROFINET | TcpGatewayBridge | Open Communication |
| B&R | POWERLINK | ExternalProcessBridge | Automation Studio / openPOWERLINK |
| Bosch Rexroth | SERCOS III | TcpGatewayBridge | ctrlX CORE / netX |
| Hilscher | Multi-protocol | TcpGatewayBridge | netX SoC |
| HMS/Anybus | Multi-protocol | TcpGatewayBridge | Anybus Communicator |
| Moxa | Multi-protocol | TcpGatewayBridge | MGate Series |
| Phoenix Contact | PROFINET/EIP | TcpGatewayBridge | AXL F Series |

## Architecture

The vendor adapter layer is built on the `BridgeInterface`, providing two bridge types:

- **ExternalProcessBridge** — communicates via `proc_open` with C/C++ SDK processes, for local SDKs
- **TcpGatewayBridge** — connects via TCP/UDP to hardware gateway devices (`stream_socket_client`), for remote/embedded gateways

### Key Classes

| Class | Purpose |
|---|------|
| `VendorProfile` | Vendor configuration (name, protocol, bridge type, SDK path, default port, device list) |
| `DeviceProfile` | Device model configuration (model, version, config overrides) |
| `VendorBridgeFactory` | Vendor registry and bridge factory |
| `DefaultVendors` | Pre-configured profiles for 8 major industrial hardware vendors |

## Usage

### Get the vendor factory from Kernel

```php
$kernel = new Kernel();
$kernel->boot();

$factory = $kernel->getVendorBridgeFactory();
```

### List all vendors

```php
foreach ($factory->listVendors() as $name => $vendor) {
    echo "$name: {$vendor->protocol} via {$vendor->bridgeType}\n";
}
```

### Create vendor bridges

```php
// Beckhoff — ExternalProcessBridge
$bridge = $factory->create('beckhoff', 'CX2030', '3.1');

// Siemens — TcpGatewayBridge
$bridge = $factory->create('siemens', 'S7-1500', 'V3.x', [
    'host' => '192.168.1.50',
]);

// Create from connection config array
$bridge = $factory->createFromConfig([
    'vendor' => 'hilscher',
    'device_model' => 'netX 90',
    'host' => '10.0.0.10',
    'port' => 5000,
]);
```

## Vendor Details

### Beckhoff (EtherCAT)

Based on TwinCAT ADS protocol. Bridge type: `external-process`. Requires local TwinCAT ADS DLL (path: `/opt/beckhoff/twincat/AdsApi/TcAdsDll`).

Supported devices: CX2030, CX5140, C6015, C6030, EK1100, EK1501

```php
$beckhoff = $factory->getVendor('beckhoff');
$device = $beckhoff->getDevice('CX2030');
// $device->configOverrides: ['ads_netid' => '0.0.0.0.1.1']
```

### Siemens (PROFINET)

Based on SIMATIC Open Communication. Bridge type: `tcp-gateway`, default port 34964.

Supported devices: S7-1200, S7-1500, ET 200SP, ET 200MP, S7-400

### B&R Automation (POWERLINK)

Supports both Automation Studio SDK and openPOWERLINK. X20BC0083 defaults to openPOWERLINK path.

Supported devices: X20CP1584, X20CP1586, ACOPOS P3, X20BC0083

### Bosch Rexroth (SERCOS III)

Connects to SERCOS III devices via ctrlX CORE or Hilscher netX gateway. ctrlX CORE uses HTTPS port 8443.

Supported devices: IndraDrive Cs, IndraDrive Mi, ctrlX CORE, HMV01

### Hilscher (Multi-protocol)

netX SoC series supports EtherCAT, PROFINET, POWERLINK, SERCOS III, EtherNet/IP. Default port 5000.

Supported devices: netX 90, netX 4000, cifX RE, comX

### HMS Anybus (Multi-protocol)

Protocol conversion gateways. Supports EtherNet/IP to Modbus/PROFIBUS/PROFINET conversion. Default port 502.

Supported devices: Anybus Communicator, Anybus X-gateway, Anybus CompactCom, Anybus Wireless Bolt

### Moxa (Multi-protocol)

MGate series industrial Ethernet gateways. Supports Modbus, PROFINET, EtherNet/IP protocol conversion. Default port 502.

Supported devices: MGate 5101-PBM-MN, MGate 5102-PBM-PN, MGate 5105-MB-EIP, MGate 5118

### Phoenix Contact (PROFINET / EtherNet/IP)

AXL F series I/O systems. PROFINET default port 34964, EtherNet/IP default port 44818.

Supported devices: AXL F BK PN, AXL F IL ETH, AXL E ETH DI16, ILC 191

## Custom Vendors

Register custom vendor profiles via `VendorBridgeFactory::register()`:

```php
$factory->register(new VendorProfile(
    name: 'my-custom-vendor',
    protocol: 'ethercat',
    bridgeType: 'external-process',
    sdkPath: '/opt/mycompany/bin/ethercat_master',
    defaultPort: 0,
    devices: [
        new DeviceProfile('MyDevice', 'V1.0', ['ads_netid' => '0.0.0.0.1.1']),
    ],
));
```
