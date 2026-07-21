# Security Guide

> [中文](../security.md)

## Input Validation

```php
use Erikwang2013\IndustrialProtocols\Security\InputValidator;

InputValidator::deviceId($input);     // alphanumeric + dash/underscore/dot, max 128
InputValidator::host($host);          // IP or hostname
InputValidator::port($port);          // 1-65535
InputValidator::modbusAddress($addr); // 0-65535
InputValidator::timeout($ms);         // 10-60000
InputValidator::frameSize($bytes, 260); // protocol max
```

## Best Practices

- Never expose industrial protocol ports to public internet
- Use firewall rules to restrict device access
- Validate all user-supplied config
- Use parameterized queries (PDO) for DB config storage
- Monitor circuit breaker events

## Frame Size Limits

| Protocol | Max |
|----------|-----|
| Modbus TCP | 260 bytes |
| BACnet | 4096 bytes |
| EtherNet/IP | 65535 bytes |

## Error Handling

| Exception | Cause |
|-----------|-------|
| `ConnectionTimeoutException` | Connection timeout |
| `ConnectionRefusedException` | Connection refused |
| `AddressOutOfRangeException` | Invalid register address |
| `FrameException` | Corrupted frame |
| `CrcException` | CRC mismatch |
| `DeviceBusyException` | Device busy |
