# Security Guide

## Input Validation

Use `InputValidator` for all public-facing API inputs. All methods are static.

```php
use IndustrialProtocols\Security\InputValidator;

// Device ID: alphanumeric, dash, underscore, dot; max 128 chars
$deviceId = InputValidator::deviceId($userInput);

// Host: IP address or hostname; max 255 chars
$host = InputValidator::host($config['host']);

// Port: 1-65535
$port = InputValidator::port($config['port']);

// Modbus register address: 0-65535
$address = InputValidator::modbusAddress('40001');

// Timeout: 10-60000 ms
$timeout = InputValidator::timeout(3000);

// Frame size: validates bytes against protocol maximum
InputValidator::frameSize($rawBytes, 260);  // Modbus TCP: 260 bytes
InputValidator::frameSize($rawBytes, 4096); // BACnet: 4096 bytes
InputValidator::frameSize($rawBytes, 65535); // EtherNet/IP: 65535 bytes
```

All validation methods throw `\InvalidArgumentException` on failure.

## Best Practices

### Network Security

1. **Never expose Modbus, BACnet, or EtherNet/IP ports to the public internet** -- these protocols have no built-in encryption or authentication
2. **Use firewall rules** to restrict device access to trusted IP ranges only
3. **Segment OT networks** from corporate IT networks using VLANs or physical isolation
4. **Use VPN tunnels** (WireGuard, OpenVPN, IPSec) when remote access to industrial devices is required

### Configuration Security

1. **Validate all user-supplied configuration** with `InputValidator` before use
2. **Use `DatabaseConfigRepository`** with parameterized queries (PDO prepared statements) when storing device configurations in a database
3. **Never hardcode credentials** in configuration files -- use environment variables or a secrets manager
4. **Set reasonable timeouts** (default 3000ms) to prevent hanging connections from resource exhaustion

### Operational Security

1. **Monitor circuit breaker events** -- repeated breaker trips indicate persistent device or network issues requiring investigation
2. **Set appropriate retry limits** (`default_retry_max` in config) to avoid thundering herd problems on reconnect
3. **Use exponential backoff** for reconnection attempts (`default_retry_backoff: 'exponential'`)
4. **Implement health check intervals** (`health_check_interval`) to detect stale connections proactively
5. **Rotate OPC UA certificates** regularly when OPC UA support is enabled (planned Phase 2)

## Frame Size Limits

All protocol frames are validated against protocol-specific maximum sizes:

| Protocol | Maximum Frame Size |
|----------|-------------------|
| Modbus TCP | 260 bytes |
| BACnet | 4096 bytes |
| EtherNet/IP | 65535 bytes |

The `InputValidator::frameSize()` method accepts the frame bytes and the protocol maximum, throwing on overflow.

## Logging Sensitive Data

The kernel's `PsrLogDriver` delegates to any PSR-3 compatible logger. Configure your PSR-3 logger to:

- **Redact IP addresses** in production logs if they are considered sensitive
- **Redact register values** if data confidentiality is required
- **Avoid logging raw protocol frames** in production -- use the `DEBUG` level for verbose wire data and suppress it in normal operation

## Configuration Example

```php
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
    'health_check_interval' => 30,
    'default_retry_max' => 3,
    'default_retry_backoff' => 'exponential',
    'default_timeout' => 3000,
];
```

## Error Handling

The library throws typed exceptions for all failure modes:

| Exception | Cause |
|-----------|-------|
| `ConnectionException` | General connection failure |
| `ConnectionTimeoutException` | Connection attempt timed out |
| `ConnectionRefusedException` | Remote device refused connection |
| `ConnectionClosedException` | Connection lost during operation |
| `AddressOutOfRangeException` | Register address outside valid range |
| `FrameException` | Malformed or corrupted protocol frame |
| `CrcException` | CRC check failed (Modbus RTU) |
| `DeviceBusyException` | Device returned busy status |
| `DeviceException` | Device reported an error |
| `ProtocolException` | Protocol-level error |
