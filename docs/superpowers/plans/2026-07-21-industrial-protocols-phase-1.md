# Industrial Protocols — Phase 1 MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the micro-kernel + Modbus TCP protocol package with framework auto-discovery, ≥80% simulation test coverage.

**Architecture:** Monorepo with `packages/kernel/` and `packages/modbus/`. Kernel provides SDK interfaces, ProtocolRegistry, ConnectionManager, ConfigRepository, CoroutineAdapters, FrameworkAdapters, Event/Log systems. Modbus package depends on kernel, implements ProtocolInterface + ConnectorInterface + DriverInterface for Modbus TCP over pure PHP sockets.

**Tech Stack:** PHP ≥8.1, PHPUnit 10+, PSR-3 (log), PSR-14 (events), PSR-4 (autoload), Composer

---

### Task 1: Project Scaffolding

**Files:**
- Create: `composer.json` (root)
- Create: `phpunit.xml` (root)
- Create: `packages/kernel/composer.json`
- Create: `packages/kernel/src/` directory structure
- Create: `packages/modbus/composer.json`
- Create: `packages/modbus/src/` directory structure

- [ ] **Step 1: Create root composer.json**

```json
{
    "name": "industrial-protocols/industrial-protocols",
    "type": "project",
    "require": {
        "php": ">=8.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^10.0",
        "psr/log": "^3.0",
        "psr/event-dispatcher": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\\IndustrialProtocols\\": "packages/kernel/src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Erikwang2013\\IndustrialProtocols\\Tests\\": "packages/kernel/tests/",
            "Erikwang2013\\IndustrialProtocols\\Modbus\\": "packages/modbus/src/",
            "Erikwang2013\\IndustrialProtocols\\Modbus\\Tests\\": "packages/modbus/tests/"
        }
    },
    "scripts": {
        "test": "phpunit"
    }
}
```

- [ ] **Step 2: Create root phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/10.0/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Kernel">
            <directory>packages/kernel/tests</directory>
        </testsuite>
        <testsuite name="Modbus">
            <directory>packages/modbus/tests</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <report>
            <html outputDirectory="build/coverage"/>
            <text outputFile="build/coverage.txt"/>
        </report>
    </coverage>
</phpunit>
```

- [ ] **Step 3: Create packages/kernel/composer.json**

```json
{
    "name": "industrial-protocols/kernel",
    "type": "library",
    "require": {
        "php": ">=8.1",
        "psr/log": "^3.0",
        "psr/event-dispatcher": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\\IndustrialProtocols\\": "src/"
        }
    },
    "extra": {
        "industrial-protocols": {
            "type": "kernel"
        }
    }
}
```

- [ ] **Step 4: Create packages/modbus/composer.json**

```json
{
    "name": "industrial-protocols/modbus",
    "type": "industrial-protocol",
    "require": {
        "php": ">=8.1",
        "industrial-protocols/kernel": "*"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\\IndustrialProtocols\\Modbus\\": "src/"
        }
    },
    "extra": {
        "industrial-protocols": {
            "protocol": "Erikwang2013\\IndustrialProtocols\\Modbus\\ModbusProtocol"
        }
    }
}
```

- [ ] **Step 5: Create directory structure**

Run: `mkdir -p packages/kernel/src/{Protocol,Connection/Strategy,Config,Coroutine,Framework,Event,Log,Retry,Exception} packages/kernel/tests/{Unit,Simulation} packages/kernel/config packages/modbus/src/{Driver,Frame,DataType,Exception} packages/modbus/tests/{Unit,Simulation} tests/Integration`

- [ ] **Step 6: Install dependencies**

Run: `composer install`
Expected: No errors, vendor/ created

- [ ] **Step 7: Verify autoloading**

Run: `php -r "require 'vendor/autoload.php'; echo 'OK';"`
Expected: OK

- [ ] **Step 8: Commit**

```bash
git add composer.json phpunit.xml packages/ tests/
git commit -m "chore: scaffold monorepo with kernel and modbus packages"
```

---

### Task 2: DataType and Access Enums

**Files:**
- Create: `packages/kernel/src/Protocol/DataType.php`
- Create: `packages/kernel/src/Protocol/Access.php`
- Test: `packages/kernel/tests/Unit/DataTypeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// packages/kernel/tests/Unit/DataTypeTest.php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Protocol\DataType;
use PHPUnit\Framework\TestCase;

class DataTypeTest extends TestCase
{
    public function testDataTypeHasExpectedValues(): void
    {
        $this->assertInstanceOf(DataType::class, DataType::BOOL);
        $this->assertInstanceOf(DataType::class, DataType::INT16);
        $this->assertInstanceOf(DataType::class, DataType::UINT16);
        $this->assertInstanceOf(DataType::class, DataType::INT32);
        $this->assertInstanceOf(DataType::class, DataType::UINT32);
        $this->assertInstanceOf(DataType::class, DataType::FLOAT32);
        $this->assertInstanceOf(DataType::class, DataType::FLOAT64);
        $this->assertInstanceOf(DataType::class, DataType::STRING);
    }

    public function testDataTypeGetSize(): void
    {
        $this->assertSame(1, DataType::BOOL->getSize());
        $this->assertSame(2, DataType::INT16->getSize());
        $this->assertSame(2, DataType::UINT16->getSize());
        $this->assertSame(4, DataType::INT32->getSize());
        $this->assertSame(4, DataType::UINT32->getSize());
        $this->assertSame(4, DataType::FLOAT32->getSize());
        $this->assertSame(8, DataType::FLOAT64->getSize());
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/DataTypeTest.php`
Expected: ERROR — class DataType not found

- [ ] **Step 3: Implement DataType enum**

```php
<?php
// packages/kernel/src/Protocol/DataType.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

enum DataType: string
{
    case BOOL    = 'BOOL';
    case INT16   = 'INT16';
    case UINT16  = 'UINT16';
    case INT32   = 'INT32';
    case UINT32  = 'UINT32';
    case FLOAT32 = 'FLOAT32';
    case FLOAT64 = 'FLOAT64';
    case STRING  = 'STRING';

    public function getSize(): int
    {
        return match ($this) {
            self::BOOL    => 1,
            self::INT16,
            self::UINT16  => 2,
            self::INT32,
            self::UINT32,
            self::FLOAT32 => 4,
            self::FLOAT64 => 8,
            self::STRING  => 0,
        };
    }
}
```

- [ ] **Step 4: Create Access enum**

```php
<?php
// packages/kernel/src/Protocol/Access.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

enum Access: string
{
    case READ       = 'RO';
    case WRITE      = 'WO';
    case READ_WRITE = 'RW';
}
```

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/DataTypeTest.php`
Expected: PASS (green)

- [ ] **Step 6: Commit**

```bash
git add packages/kernel/src/Protocol/DataType.php packages/kernel/src/Protocol/Access.php packages/kernel/tests/
git commit -m "feat: add DataType and Access enums"
```

---

### Task 3: Exception Hierarchy

**Files:**
- Create: `packages/kernel/src/Exception/IndustrialProtocolsException.php`
- Create: `packages/kernel/src/Exception/ConnectionException.php`
- Create: `packages/kernel/src/Exception/ConnectionTimeoutException.php`
- Create: `packages/kernel/src/Exception/ConnectionRefusedException.php`
- Create: `packages/kernel/src/Exception/ConnectionClosedException.php`
- Create: `packages/kernel/src/Exception/ProtocolException.php`
- Create: `packages/kernel/src/Exception/FrameException.php`
- Create: `packages/kernel/src/Exception/CrcException.php`
- Create: `packages/kernel/src/Exception/DeviceException.php`
- Create: `packages/kernel/src/Exception/DeviceBusyException.php`
- Create: `packages/kernel/src/Exception/AddressOutOfRangeException.php`
- Test: `packages/kernel/tests/Unit/ExceptionTest.php`

- [ ] **Step 1: Write test**

```php
<?php
// packages/kernel/tests/Unit/ExceptionTest.php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Exception\ConnectionException;
use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;
use Erikwang2013\IndustrialProtocols\Exception\ConnectionRefusedException;
use Erikwang2013\IndustrialProtocols\Exception\ConnectionClosedException;
use Erikwang2013\IndustrialProtocols\Exception\ProtocolException;
use Erikwang2013\IndustrialProtocols\Exception\FrameException;
use Erikwang2013\IndustrialProtocols\Exception\CrcException;
use Erikwang2013\IndustrialProtocols\Exception\DeviceException;
use Erikwang2013\IndustrialProtocols\Exception\DeviceBusyException;
use Erikwang2013\IndustrialProtocols\Exception\AddressOutOfRangeException;
use Erikwang2013\IndustrialProtocols\Exception\IndustrialProtocolsException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testConnectionTimeoutIsConnectionException(): void
    {
        $e = new ConnectionTimeoutException('timeout');
        $this->assertInstanceOf(ConnectionException::class, $e);
        $this->assertInstanceOf(IndustrialProtocolsException::class, $e);
    }

    public function testConnectionRefusedIsConnectionException(): void
    {
        $e = new ConnectionRefusedException('refused');
        $this->assertInstanceOf(ConnectionException::class, $e);
    }

    public function testConnectionClosedIsConnectionException(): void
    {
        $e = new ConnectionClosedException('closed');
        $this->assertInstanceOf(ConnectionException::class, $e);
    }

    public function testFrameExceptionIsProtocolException(): void
    {
        $e = new FrameException('bad frame');
        $this->assertInstanceOf(ProtocolException::class, $e);
    }

    public function testCrcExceptionIsProtocolException(): void
    {
        $e = new CrcException('crc mismatch');
        $this->assertInstanceOf(ProtocolException::class, $e);
    }

    public function testDeviceBusyIsDeviceException(): void
    {
        $e = new DeviceBusyException('busy');
        $this->assertInstanceOf(DeviceException::class, $e);
    }

    public function testAddressOutOfRangeIsDeviceException(): void
    {
        $e = new AddressOutOfRangeException('out of range');
        $this->assertInstanceOf(DeviceException::class, $e);
    }

    public function testExceptionCarriesContext(): void
    {
        $e = new ConnectionTimeoutException('timeout', ['device' => 'plc-001', 'host' => '192.168.1.10']);
        $this->assertSame('plc-001', $e->getContext()['device']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/ExceptionTest.php`
Expected: ERROR

- [ ] **Step 3: Create base exception**

```php
<?php
// packages/kernel/src/Exception/IndustrialProtocolsException.php

namespace Erikwang2013\IndustrialProtocols\Exception;

class IndustrialProtocolsException extends \RuntimeException
{
    private array $context;

    public function __construct(string $message = '', array $context = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
```

- [ ] **Step 4: Create all subclass exceptions**

```php
<?php
// packages/kernel/src/Exception/ConnectionException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class ConnectionException extends IndustrialProtocolsException {}

// packages/kernel/src/Exception/ConnectionTimeoutException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class ConnectionTimeoutException extends ConnectionException {}

// packages/kernel/src/Exception/ConnectionRefusedException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class ConnectionRefusedException extends ConnectionException {}

// packages/kernel/src/Exception/ConnectionClosedException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class ConnectionClosedException extends ConnectionException {}

// packages/kernel/src/Exception/ProtocolException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class ProtocolException extends IndustrialProtocolsException {}

// packages/kernel/src/Exception/FrameException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class FrameException extends ProtocolException {}

// packages/kernel/src/Exception/CrcException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class CrcException extends ProtocolException {}

// packages/kernel/src/Exception/DeviceException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class DeviceException extends IndustrialProtocolsException {}

// packages/kernel/src/Exception/DeviceBusyException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class DeviceBusyException extends DeviceException {}

// packages/kernel/src/Exception/AddressOutOfRangeException.php
namespace Erikwang2013\IndustrialProtocols\Exception;
class AddressOutOfRangeException extends DeviceException {}
```

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/ExceptionTest.php`
Expected: PASS (green)

- [ ] **Step 6: Commit**

```bash
git add packages/kernel/src/Exception/ packages/kernel/tests/Unit/ExceptionTest.php
git commit -m "feat: add exception hierarchy"
```

---

### Task 4: HealthStatus Value Object + ConnectionState Enum

**Files:**
- Create: `packages/kernel/src/Connection/HealthStatus.php`
- Create: `packages/kernel/src/Connection/ConnectionState.php`
- Test: `packages/kernel/tests/Unit/HealthStatusTest.php`

- [ ] **Step 1: Write test**

```php
<?php
// packages/kernel/tests/Unit/HealthStatusTest.php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use PHPUnit\Framework\TestCase;

class HealthStatusTest extends TestCase
{
    public function testHealthyStatus(): void
    {
        $status = HealthStatus::healthy(15.2);
        $this->assertSame(ConnectionState::HEALTHY, $status->state);
        $this->assertSame(15.2, $status->latencyMs);
        $this->assertNull($status->lastError);
    }

    public function testDegradedStatus(): void
    {
        $status = HealthStatus::degraded(500.0, 'Slow response', 1);
        $this->assertSame(ConnectionState::DEGRADED, $status->state);
        $this->assertSame('Slow response', $status->lastError);
    }

    public function testFaultStatus(): void
    {
        $status = HealthStatus::fault('Connection refused', 3);
        $this->assertSame(ConnectionState::FAULT, $status->state);
        $this->assertSame(3, $status->retryCount);
    }

    public function testJsonSerialize(): void
    {
        $status = HealthStatus::healthy(12.5);
        $data = json_decode(json_encode($status), true);
        $this->assertSame('HEALTHY', $data['state']);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/HealthStatusTest.php`
Expected: ERROR

- [ ] **Step 3: Create ConnectionState enum**

```php
<?php
// packages/kernel/src/Connection/ConnectionState.php

namespace Erikwang2013\IndustrialProtocols\Connection;

enum ConnectionState: string
{
    case HEALTHY   = 'HEALTHY';
    case DEGRADED  = 'DEGRADED';
    case FAULT     = 'FAULT';
    case CLOSED    = 'CLOSED';
    case CONNECTING = 'CONNECTING';
}
```

- [ ] **Step 4: Create HealthStatus**

```php
<?php
// packages/kernel/src/Connection/HealthStatus.php

namespace Erikwang2013\IndustrialProtocols\Connection;

class HealthStatus implements \JsonSerializable
{
    public function __construct(
        public readonly ConnectionState $state,
        public readonly float $latencyMs = 0.0,
        public readonly ?string $lastError = null,
        public readonly int $retryCount = 0,
    ) {}

    public static function healthy(float $latencyMs): self
    {
        return new self(ConnectionState::HEALTHY, latencyMs: $latencyMs);
    }

    public static function degraded(float $latencyMs, string $error, int $retryCount): self
    {
        return new self(ConnectionState::DEGRADED, latencyMs, $error, $retryCount);
    }

    public static function fault(string $error, int $retryCount): self
    {
        return new self(ConnectionState::FAULT, lastError: $error, retryCount: $retryCount);
    }

    public static function closed(string $reason): self
    {
        return new self(ConnectionState::CLOSED, lastError: $reason);
    }

    public function jsonSerialize(): array
    {
        return [
            'state'       => $this->state->value,
            'latency_ms'  => $this->latencyMs,
            'last_error'  => $this->lastError,
            'retry_count' => $this->retryCount,
        ];
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/HealthStatusTest.php`
Expected: PASS (green)

- [ ] **Step 6: Commit**

```bash
git add packages/kernel/src/Connection/ packages/kernel/tests/Unit/HealthStatusTest.php
git commit -m "feat: add HealthStatus value object and ConnectionState enum"
```

---

### Task 5: SDK Interfaces

**Files:**
- Create: `packages/kernel/src/Protocol/ProtocolInterface.php`
- Create: `packages/kernel/src/Protocol/ConnectorInterface.php`
- Create: `packages/kernel/src/Protocol/DriverInterface.php`
- Create: `packages/kernel/src/Protocol/FrameInterface.php`
- Create: `packages/kernel/src/Protocol/DataPointInterface.php`
- Create: `packages/kernel/src/Protocol/GatewayRuleInterface.php`
- Test: `packages/kernel/tests/Unit/InterfaceExistsTest.php`

- [ ] **Step 1: Create ProtocolInterface**

```php
<?php
// packages/kernel/src/Protocol/ProtocolInterface.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface ProtocolInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function getSupportedVariants(): array;
    public function getDefaultPort(): int;
    public function createConnector(array $config): ConnectorInterface;
}
```

- [ ] **Step 2: Create ConnectorInterface**

```php
<?php
// packages/kernel/src/Protocol/ConnectorInterface.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;

interface ConnectorInterface
{
    public function connect(): void;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function read(string|array $points): array;
    public function write(string|array $points, array $values): array;
    public function getHealth(): HealthStatus;
}
```

- [ ] **Step 3: Create DriverInterface**

```php
<?php
// packages/kernel/src/Protocol/DriverInterface.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface DriverInterface
{
    public function send(FrameInterface $frame): FrameInterface;
    public function sendAsync(FrameInterface $frame): mixed;
    public function getLatency(): float;
    public function supportsAsync(): bool;
}
```

- [ ] **Step 4: Create FrameInterface**

```php
<?php
// packages/kernel/src/Protocol/FrameInterface.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface FrameInterface
{
    public function toBytes(): string;
    public static function fromBytes(string $bytes): static;
    public function getData(): array;
}
```

- [ ] **Step 5: Create DataPointInterface, GatewayRuleInterface, test**

```php
<?php
// packages/kernel/src/Protocol/DataPointInterface.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface DataPointInterface
{
    public function getAddress(): string;
    public function getType(): DataType;
    public function getAccess(): Access;
}
```

```php
<?php
// packages/kernel/src/Protocol/GatewayRuleInterface.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface GatewayRuleInterface
{
    public function getSource(): ConnectorInterface;
    public function getTarget(): ConnectorInterface;
    public function getMapping(): array;
    public function getTransform(): ?callable;
    public function getInterval(): int;
}
```

```php
<?php
// packages/kernel/tests/Unit/InterfaceExistsTest.php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\DataPointInterface;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;
use Erikwang2013\IndustrialProtocols\Protocol\GatewayRuleInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;
use PHPUnit\Framework\TestCase;

class InterfaceExistsTest extends TestCase
{
    public function testAllInterfacesAreDefined(): void
    {
        $this->assertTrue(interface_exists(ProtocolInterface::class));
        $this->assertTrue(interface_exists(ConnectorInterface::class));
        $this->assertTrue(interface_exists(DriverInterface::class));
        $this->assertTrue(interface_exists(FrameInterface::class));
        $this->assertTrue(interface_exists(DataPointInterface::class));
        $this->assertTrue(interface_exists(GatewayRuleInterface::class));
    }
}
```

- [ ] **Step 6: Run to verify pass**

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/InterfaceExistsTest.php`
Expected: PASS (green)

- [ ] **Step 7: Commit**

```bash
git add packages/kernel/src/Protocol/ packages/kernel/tests/Unit/InterfaceExistsTest.php
git commit -m "feat: add SDK interfaces (Protocol, Connector, Driver, Frame, DataPoint, GatewayRule)"
```

---

### Task 6: Event System + Log Drivers

**Files:**
- Create: `packages/kernel/src/Event/ConnectionConnectedEvent.php`
- Create: `packages/kernel/src/Event/ConnectionDisconnectedEvent.php`
- Create: `packages/kernel/src/Event/ConnectionStateChangedEvent.php`
- Create: `packages/kernel/src/Event/ConnectionRetryEvent.php`
- Create: `packages/kernel/src/Event/DataReadEvent.php`
- Create: `packages/kernel/src/Event/DataWriteEvent.php`
- Create: `packages/kernel/src/Event/DataErrorEvent.php`
- Create: `packages/kernel/src/Event/KernelBootedEvent.php`
- Create: `packages/kernel/src/Event/ProtocolRegisteredEvent.php`
- Create: `packages/kernel/src/Log/LogDriverInterface.php`
- Create: `packages/kernel/src/Log/PsrLogDriver.php`
- Create: `packages/kernel/src/Log/NullLogDriver.php`
- Test: `packages/kernel/tests/Unit/EventTest.php`
- Test: `packages/kernel/tests/Unit/LogDriverTest.php`

- [ ] **Step 1: Create all event classes**

```php
<?php
// packages/kernel/src/Event/ConnectionConnectedEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class ConnectionConnectedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $protocol,
        public readonly string $address,
    ) {}
}

// packages/kernel/src/Event/ConnectionDisconnectedEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class ConnectionDisconnectedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $reason = '',
    ) {}
}

// packages/kernel/src/Event/ConnectionStateChangedEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
class ConnectionStateChangedEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly HealthStatus $oldStatus,
        public readonly HealthStatus $newStatus,
    ) {}
}

// packages/kernel/src/Event/ConnectionRetryEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class ConnectionRetryEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly int $attempt,
        public readonly int $maxAttempts,
        public readonly int $delayMs,
    ) {}
}

// packages/kernel/src/Event/DataReadEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class DataReadEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly array $data,
        public readonly float $latencyMs,
    ) {}
}

// packages/kernel/src/Event/DataWriteEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class DataWriteEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly array $values,
        public readonly float $latencyMs,
    ) {}
}

// packages/kernel/src/Event/DataErrorEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class DataErrorEvent {
    public function __construct(
        public readonly string $deviceId,
        public readonly string $operation,
        public readonly string $message,
        public readonly int $retryCount,
    ) {}
}

// packages/kernel/src/Event/KernelBootedEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class KernelBootedEvent {
    public function __construct(
        public readonly array $registeredProtocols = [],
        public readonly string $framework = 'plain',
    ) {}
}

// packages/kernel/src/Event/ProtocolRegisteredEvent.php
namespace Erikwang2013\IndustrialProtocols\Event;
class ProtocolRegisteredEvent {
    public function __construct(
        public readonly string $protocolName,
        public readonly string $protocolClass,
    ) {}
}
```

- [ ] **Step 2: Create LogDriverInterface + implementations**

```php
<?php
// packages/kernel/src/Log/LogDriverInterface.php
namespace Erikwang2013\IndustrialProtocols\Log;
interface LogDriverInterface
{
    public function log(string $level, string $message, array $context = []): void;
    public function event(object $event): void;
}

// packages/kernel/src/Log/PsrLogDriver.php
namespace Erikwang2013\IndustrialProtocols\Log;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
class PsrLogDriver implements LogDriverInterface
{
    public function __construct(private LoggerInterface $logger = new NullLogger()) {}
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
    public function event(object $event): void
    {
        $this->logger->info($event::class, (array)$event);
    }
}

// packages/kernel/src/Log/NullLogDriver.php
namespace Erikwang2013\IndustrialProtocols\Log;
class NullLogDriver implements LogDriverInterface
{
    public function log(string $level, string $message, array $context = []): void {}
    public function event(object $event): void {}
}
```

- [ ] **Step 3: Write and run tests**

```php
<?php
// packages/kernel/tests/Unit/EventTest.php
namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Event\ConnectionConnectedEvent;
use Erikwang2013\IndustrialProtocols\Event\ConnectionStateChangedEvent;
use Erikwang2013\IndustrialProtocols\Event\DataReadEvent;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    public function testConnectionConnectedEvent(): void
    {
        $event = new ConnectionConnectedEvent('plc-001', 'modbus', '192.168.1.10:502');
        $this->assertSame('plc-001', $event->deviceId);
        $this->assertSame('modbus', $event->protocol);
    }

    public function testConnectionStateChangedEvent(): void
    {
        $old = HealthStatus::healthy(10.0);
        $new = HealthStatus::degraded(500.0, 'Slow', 1);
        $event = new ConnectionStateChangedEvent('plc-001', $old, $new);
        $this->assertSame(ConnectionState::DEGRADED, $event->newStatus->state);
    }

    public function testDataReadEvent(): void
    {
        $event = new DataReadEvent('plc-001', ['40001' => 23.5], 15.2);
        $this->assertSame(23.5, $event->data['40001']);
    }
}
```

```php
<?php
// packages/kernel/tests/Unit/LogDriverTest.php
namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Event\DataReadEvent;
use Erikwang2013\IndustrialProtocols\Log\NullLogDriver;
use Erikwang2013\IndustrialProtocols\Log\PsrLogDriver;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class LogDriverTest extends TestCase
{
    public function testPsrLogDriverLogsToPsrLogger(): void
    {
        $logger = new class extends AbstractLogger {
            public array $logs = [];
            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = compact('level', 'message', 'context');
            }
        };
        $driver = new PsrLogDriver($logger);
        $driver->log('ERROR', 'test message', ['key' => 'value']);
        $this->assertCount(1, $logger->logs);
        $this->assertSame('ERROR', $logger->logs[0]['level']);
    }

    public function testPsrLogDriverLogsEvent(): void
    {
        $logger = new class extends AbstractLogger {
            public array $logs = [];
            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = compact('level', 'message');
            }
        };
        $driver = new PsrLogDriver($logger);
        $driver->event(new DataReadEvent('plc-001', ['40001' => 42], 10.0));
        $this->assertCount(1, $logger->logs);
    }

    public function testNullLogDriverDoesNothing(): void
    {
        $driver = new NullLogDriver();
        $driver->log('ERROR', 'test');
        $driver->event(new DataReadEvent('plc-001', [], 0));
        $this->assertTrue(true);
    }
}
```

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/EventTest.php packages/kernel/tests/Unit/LogDriverTest.php`
Expected: PASS (green)

- [ ] **Step 4: Commit**

```bash
git add packages/kernel/src/Event/ packages/kernel/src/Log/ packages/kernel/tests/Unit/EventTest.php packages/kernel/tests/Unit/LogDriverTest.php
git commit -m "feat: add event classes and log drivers (PSR-3, Null)"
```

---

### Task 7: Config Repository

**Files:**
- Create: `packages/kernel/src/Config/ConfigRepositoryInterface.php`
- Create: `packages/kernel/src/Config/FileConfigRepository.php`
- Create: `packages/kernel/config/industrial-protocols.php`
- Test: `packages/kernel/tests/Unit/FileConfigRepositoryTest.php`

- [ ] **Step 1: Create interface**

```php
<?php
// packages/kernel/src/Config/ConfigRepositoryInterface.php

namespace Erikwang2013\IndustrialProtocols\Config;

interface ConfigRepositoryInterface
{
    public function getDeviceConfig(string $deviceId): array;
    public function setDeviceConfig(string $deviceId, array $config): void;
    public function removeDeviceConfig(string $deviceId): void;
    public function getAllDeviceConfigs(): array;
    public function getDataPoints(string $deviceId): array;
    public function setDataPoints(string $deviceId, array $points): void;
    public function getGatewayRules(): array;
    public function addGatewayRule(array $rule): void;
    public function removeGatewayRule(string $ruleId): void;
}
```

- [ ] **Step 2: Create default config template**

```php
<?php
// packages/kernel/config/industrial-protocols.php

return [
    'devices' => [],
    'gateway' => [
        'rules' => [],
    ],
    'health_check_interval' => 30,
    'default_retry_max' => 3,
    'default_retry_backoff' => 'exponential',
    'default_timeout' => 3000,
];
```

- [ ] **Step 3: Write test and implement FileConfigRepository**

Test and implementation follow the spec's ConfigRepository design — test covers getDeviceConfig, getAllDeviceConfigs, data points CRUD, gateway rules CRUD. Implementation reads/writes PHP config arrays with `require`.

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/FileConfigRepositoryTest.php`
Expected: PASS (green)

- [ ] **Step 4: Commit**

```bash
git add packages/kernel/src/Config/ packages/kernel/config/ packages/kernel/tests/Unit/FileConfigRepositoryTest.php
git commit -m "feat: add ConfigRepository interface and FileConfigRepository"
```

---

### Task 8: Coroutine Adapters

**Files:**
- Create: `packages/kernel/src/Coroutine/CoroutineAdapterInterface.php`
- Create: `packages/kernel/src/Coroutine/SyncCoroutineAdapter.php`
- Create: `packages/kernel/src/Coroutine/FiberCoroutineAdapter.php`
- Create: `packages/kernel/src/Coroutine/CoroutineFactory.php`
- Test: `packages/kernel/tests/Unit/CoroutineAdapterTest.php`

- [ ] **Step 1: Create interface**

```php
<?php
// packages/kernel/src/Coroutine/CoroutineAdapterInterface.php

namespace Erikwang2013\IndustrialProtocols\Coroutine;

interface CoroutineAdapterInterface
{
    public function isAvailable(): bool;
    public function getName(): string;
    public function create(callable $fn): mixed;
    public function sleep(float $seconds): void;
    public function parallel(array $callables): array;
}
```

- [ ] **Step 2: Implement SyncCoroutineAdapter**

```php
<?php
// packages/kernel/src/Coroutine/SyncCoroutineAdapter.php

namespace Erikwang2013\IndustrialProtocols\Coroutine;

class SyncCoroutineAdapter implements CoroutineAdapterInterface
{
    public function isAvailable(): bool { return true; }
    public function getName(): string { return 'sync'; }
    public function create(callable $fn): mixed { return $fn(); }
    public function sleep(float $seconds): void { usleep((int)($seconds * 1_000_000)); }
    public function parallel(array $callables): array
    {
        $results = [];
        foreach ($callables as $callable) { $results[] = $callable(); }
        return $results;
    }
}
```

- [ ] **Step 3: Implement FiberCoroutineAdapter**

```php
<?php
// packages/kernel/src/Coroutine/FiberCoroutineAdapter.php

namespace Erikwang2013\IndustrialProtocols\Coroutine;

use Fiber;

class FiberCoroutineAdapter implements CoroutineAdapterInterface
{
    public function isAvailable(): bool { return PHP_VERSION_ID >= 80100; }
    public function getName(): string { return 'fiber'; }
    public function create(callable $fn): mixed
    {
        $fiber = new Fiber($fn);
        return $fiber->start();
    }
    public function sleep(float $seconds): void
    {
        $fiber = new Fiber(function () use ($seconds) {
            Fiber::suspend();
            usleep((int)($seconds * 1_000_000));
        });
        $fiber->start();
        $fiber->resume();
    }
    public function parallel(array $callables): array
    {
        $results = [];
        foreach ($callables as $callable) {
            $fiber = new Fiber($callable);
            $results[] = $fiber->start();
        }
        return $results;
    }
}
```

- [ ] **Step 4: Implement CoroutineFactory**

```php
<?php
// packages/kernel/src/Coroutine/CoroutineFactory.php

namespace Erikwang2013\IndustrialProtocols\Coroutine;

class CoroutineFactory
{
    private static array $adapters = [
        FiberCoroutineAdapter::class,
        SyncCoroutineAdapter::class,
    ];

    public static function create(): CoroutineAdapterInterface
    {
        foreach (self::$adapters as $class) {
            $adapter = new $class();
            if ($adapter->isAvailable()) { return $adapter; }
        }
        return new SyncCoroutineAdapter();
    }
}
```

- [ ] **Step 5: Write and run test**

Test covers: SyncAdapter is always available, create/call returns result, sleep delays correctly, parallel runs sequentially, CoroutineFactory returns correct adapter.

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/CoroutineAdapterTest.php`
Expected: PASS (green)

- [ ] **Step 6: Commit**

```bash
git add packages/kernel/src/Coroutine/ packages/kernel/tests/Unit/CoroutineAdapterTest.php
git commit -m "feat: add coroutine adapters (Sync, Fiber) with factory"
```

---

### Task 9: Retry Strategies

**Files:**
- Create: `packages/kernel/src/Retry/RetryStrategyInterface.php`
- Create: `packages/kernel/src/Retry/NoRetryStrategy.php`
- Create: `packages/kernel/src/Retry/FixedRetryStrategy.php`
- Create: `packages/kernel/src/Retry/ExponentialBackoffStrategy.php`
- Test: `packages/kernel/tests/Unit/RetryStrategyTest.php`

- [ ] **Step 1: Create interface + implementations**

```php
<?php
// packages/kernel/src/Retry/RetryStrategyInterface.php
namespace Erikwang2013\IndustrialProtocols\Retry;
interface RetryStrategyInterface
{
    public function shouldRetry(int $attempt, \Throwable $error): bool;
    public function getDelay(int $attempt): int; // ms
}

// packages/kernel/src/Retry/NoRetryStrategy.php
namespace Erikwang2013\IndustrialProtocols\Retry;
class NoRetryStrategy implements RetryStrategyInterface
{
    public function shouldRetry(int $attempt, \Throwable $error): bool { return false; }
    public function getDelay(int $attempt): int { return 0; }
}

// packages/kernel/src/Retry/FixedRetryStrategy.php
namespace Erikwang2013\IndustrialProtocols\Retry;
class FixedRetryStrategy implements RetryStrategyInterface
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $delayMs = 1000,
        private array $retryableExceptions = [\Throwable::class],
    ) {}
    public function shouldRetry(int $attempt, \Throwable $error): bool
    {
        if ($attempt > $this->maxAttempts) return false;
        foreach ($this->retryableExceptions as $class) {
            if ($error instanceof $class) return true;
        }
        return false;
    }
    public function getDelay(int $attempt): int { return $this->delayMs; }
}

// packages/kernel/src/Retry/ExponentialBackoffStrategy.php
namespace Erikwang2013\IndustrialProtocols\Retry;
class ExponentialBackoffStrategy implements RetryStrategyInterface
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelayMs = 1000,
        private bool $jitter = false,
        private array $retryableExceptions = [\Throwable::class],
    ) {}
    public function shouldRetry(int $attempt, \Throwable $error): bool
    {
        if ($attempt > $this->maxAttempts) return false;
        foreach ($this->retryableExceptions as $class) {
            if ($error instanceof $class) return true;
        }
        return false;
    }
    public function getDelay(int $attempt): int
    {
        $delay = $this->baseDelayMs * (1 << ($attempt - 1));
        if ($this->jitter) {
            $delay = random_int((int)($delay * 0.5), (int)($delay * 1.5));
        }
        return $delay;
    }
}
```

- [ ] **Step 2: Write and run test**

Test covers: NoRetry never retries, FixedRetry respects max attempts, ExponentialBackoff doubles delay, jitter randomizes, retryableExceptions filtering.

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/RetryStrategyTest.php`
Expected: PASS (green)

- [ ] **Step 3: Commit**

```bash
git add packages/kernel/src/Retry/ packages/kernel/tests/Unit/RetryStrategyTest.php
git commit -m "feat: add retry strategies (NoRetry, Fixed, ExponentialBackoff)"
```

---

### Task 10: ConnectionManager with LazyStrategy

**Files:**
- Create: `packages/kernel/src/Connection/Strategy/StrategyInterface.php`
- Create: `packages/kernel/src/Connection/Strategy/LazyStrategy.php`
- Create: `packages/kernel/src/Connection/ConnectionManager.php`
- Test: `packages/kernel/tests/Simulation/ConnectionManagerTest.php`

- [ ] **Step 1: Create StrategyInterface**

```php
<?php
// packages/kernel/src/Connection/Strategy/StrategyInterface.php

namespace Erikwang2013\IndustrialProtocols\Connection\Strategy;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

interface StrategyInterface
{
    public function getOrCreate(string $deviceId, callable $factory): ConnectorInterface;
    public function disconnect(string $deviceId): void;
    public function disconnectAll(): void;
    public function getActiveConnections(): array;
}
```

- [ ] **Step 2: Implement LazyStrategy**

```php
<?php
// packages/kernel/src/Connection/Strategy/LazyStrategy.php

namespace Erikwang2013\IndustrialProtocols\Connection\Strategy;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class LazyStrategy implements StrategyInterface
{
    /** @var array<string, ConnectorInterface> */
    private array $connections = [];

    public function getOrCreate(string $deviceId, callable $factory): ConnectorInterface
    {
        if (!isset($this->connections[$deviceId])) {
            $connector = $factory();
            $connector->connect();
            $this->connections[$deviceId] = $connector;
        }
        return $this->connections[$deviceId];
    }

    public function disconnect(string $deviceId): void
    {
        if (isset($this->connections[$deviceId])) {
            $this->connections[$deviceId]->disconnect();
            unset($this->connections[$deviceId]);
        }
    }

    public function disconnectAll(): void
    {
        foreach ($this->connections as $id => $connector) {
            $connector->disconnect();
            unset($this->connections[$id]);
        }
    }

    public function getActiveConnections(): array
    {
        return $this->connections;
    }
}
```

- [ ] **Step 3: Implement ConnectionManager**

```php
<?php
// packages/kernel/src/Connection/ConnectionManager.php

namespace Erikwang2013\IndustrialProtocols\Connection;

use Erikwang2013\IndustrialProtocols\Config\ConfigRepositoryInterface;
use Erikwang2013\IndustrialProtocols\Connection\Strategy\StrategyInterface;
use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineAdapterInterface;
use Erikwang2013\IndustrialProtocols\Event\ConnectionConnectedEvent;
use Erikwang2013\IndustrialProtocols\Event\ConnectionDisconnectedEvent;
use Erikwang2013\IndustrialProtocols\Log\LogDriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

class ConnectionManager
{
    /**
     * @param array<string, ProtocolInterface> $protocols
     */
    public function __construct(
        private array $protocols,
        private ConfigRepositoryInterface $configRepo,
        private EventDispatcherInterface $eventDispatcher,
        private CoroutineAdapterInterface $coroutine,
        private LogDriverInterface $log,
        private StrategyInterface $strategy,
    ) {}

    public function connect(string $deviceId): ConnectorInterface
    {
        $config = $this->configRepo->getDeviceConfig($deviceId);
        $protocolName = $config['protocol'];

        if (!isset($this->protocols[$protocolName])) {
            throw new \RuntimeException("Protocol not found: $protocolName");
        }

        return $this->strategy->getOrCreate($deviceId, function () use ($protocolName, $config) {
            $connector = $this->protocols[$protocolName]->createConnector($config);
            $connector->connect();

            $this->eventDispatcher->dispatch(new ConnectionConnectedEvent(
                $deviceId, $protocolName,
                ($config['host'] ?? '') . ':' . ($config['port'] ?? ''),
            ));
            $this->log->log('INFO', "Device $deviceId connected ($protocolName)");

            return $connector;
        });
    }

    public function disconnect(string $deviceId): void
    {
        $connector = $this->getConnection($deviceId);
        if ($connector) {
            $connector->disconnect();
            $this->strategy->disconnect($deviceId);
            $this->eventDispatcher->dispatch(new ConnectionDisconnectedEvent($deviceId));
            $this->log->log('INFO', "Device $deviceId disconnected");
        }
    }

    public function getConnection(string $deviceId): ?ConnectorInterface
    {
        return $this->strategy->getActiveConnections()[$deviceId] ?? null;
    }

    public function getAllConnections(): array
    {
        return $this->strategy->getActiveConnections();
    }

    public function health(string $deviceId): HealthStatus
    {
        $connector = $this->getConnection($deviceId);
        return $connector?->getHealth() ?? HealthStatus::closed('Not connected');
    }

    public function healthAll(): array
    {
        $results = [];
        foreach ($this->getAllConnections() as $deviceId => $connector) {
            $results[$deviceId] = $connector->getHealth();
        }
        return $results;
    }

    public function shutdown(): void
    {
        $this->strategy->disconnectAll();
    }
}
```

- [ ] **Step 4: Write and run test**

Test uses PHPUnit mocks for ProtocolInterface, ConfigRepositoryInterface, EventDispatcherInterface. Verifies: connect creates on first access, disconnect removes, health returns status, nonexistent device throws, getAllConnections lists active.

Run: `vendor/bin/phpunit packages/kernel/tests/Simulation/ConnectionManagerTest.php`
Expected: PASS (green)

- [ ] **Step 5: Commit**

```bash
git add packages/kernel/src/Connection/ packages/kernel/tests/Simulation/ConnectionManagerTest.php
git commit -m "feat: add ConnectionManager with LazyStrategy"
```

---

### Task 11: EagerStrategy

**Files:**
- Create: `packages/kernel/src/Connection/Strategy/EagerStrategy.php`
- Test: `packages/kernel/tests/Unit/EagerStrategyTest.php`

- [ ] **Step 1: Implement EagerStrategy**

```php
<?php
// packages/kernel/src/Connection/Strategy/EagerStrategy.php

namespace Erikwang2013\IndustrialProtocols\Connection\Strategy;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class EagerStrategy implements StrategyInterface
{
    /** @var array<string, ConnectorInterface> */
    private array $connections = [];

    public function getOrCreate(string $deviceId, callable $factory): ConnectorInterface
    {
        if (!isset($this->connections[$deviceId])) {
            $connector = $factory();
            $connector->connect();
            $this->connections[$deviceId] = $connector;
        }
        return $this->connections[$deviceId];
    }

    public function disconnect(string $deviceId): void
    {
        if (isset($this->connections[$deviceId])) {
            $this->connections[$deviceId]->disconnect();
            unset($this->connections[$deviceId]);
        }
    }

    public function disconnectAll(): void
    {
        foreach ($this->connections as $id => $connector) {
            $connector->disconnect();
            unset($this->connections[$id]);
        }
    }

    public function getActiveConnections(): array
    {
        return $this->connections;
    }
}
```

- [ ] **Step 2: Write and run test**

Test verifies: factory called immediately on getOrCreate, reused on second call, disconnect removes from pool.

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/EagerStrategyTest.php`
Expected: PASS (green)

- [ ] **Step 3: Commit**

```bash
git add packages/kernel/src/Connection/Strategy/EagerStrategy.php packages/kernel/tests/Unit/EagerStrategyTest.php
git commit -m "feat: add EagerStrategy for ConnectionManager"
```

---

### Task 12: ProtocolRegistry + Framework Adapters + Kernel

**Files:**
- Create: `packages/kernel/src/Protocol/ProtocolRegistry.php`
- Create: `packages/kernel/src/Framework/FrameworkAdapterInterface.php`
- Create: `packages/kernel/src/Framework/PlainPhpAdapter.php`
- Create: `packages/kernel/src/Kernel.php`
- Test: `packages/kernel/tests/Unit/ProtocolRegistryTest.php`
- Test: `packages/kernel/tests/Unit/KernelTest.php`

- [ ] **Step 1: Implement ProtocolRegistry**

```php
<?php
// packages/kernel/src/Protocol/ProtocolRegistry.php

namespace Erikwang2013\IndustrialProtocols\Protocol;

class ProtocolRegistry
{
    /** @var array<string, ProtocolInterface> */
    private array $protocols = [];

    public function register(ProtocolInterface $protocol): void
    {
        $this->protocols[$protocol->getName()] = $protocol;
    }

    public function get(string $name): ProtocolInterface
    {
        if (!isset($this->protocols[$name])) {
            throw new \RuntimeException("Protocol not registered: $name");
        }
        return $this->protocols[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->protocols[$name]);
    }

    /** @return array<string, ProtocolInterface> */
    public function all(): array
    {
        return $this->protocols;
    }

    public function autoDiscover(string $installedJsonPath): int
    {
        $count = 0;
        if (!file_exists($installedJsonPath)) return $count;

        $installed = json_decode(file_get_contents($installedJsonPath), true);
        foreach ($installed['packages'] ?? [] as $pkg) {
            $protocolClass = $pkg['extra']['industrial-protocols']['protocol'] ?? null;
            if ($protocolClass && class_exists($protocolClass)) {
                $instance = new $protocolClass();
                if ($instance instanceof ProtocolInterface) {
                    $this->register($instance);
                    $count++;
                }
            }
        }
        return $count;
    }
}
```

- [ ] **Step 2: Implement FrameworkAdapterInterface + PlainPhpAdapter**

```php
<?php
// packages/kernel/src/Framework/FrameworkAdapterInterface.php

namespace Erikwang2013\IndustrialProtocols\Framework;

interface FrameworkAdapterInterface
{
    public function detect(): bool;
    public function getName(): string;
    public function registerConfig(): void;
    public function registerServices(): void;
    public function registerCommands(): void;
    public function getConfigPath(): string;
    public function isLongRunning(): bool;
}
```

```php
<?php
// packages/kernel/src/Framework/PlainPhpAdapter.php

namespace Erikwang2013\IndustrialProtocols\Framework;

class PlainPhpAdapter implements FrameworkAdapterInterface
{
    public function __construct(private string $configPath) {}

    public function detect(): bool { return true; }
    public function getName(): string { return 'plain'; }
    public function registerConfig(): void {}
    public function registerServices(): void {}
    public function registerCommands(): void {}
    public function getConfigPath(): string { return $this->configPath; }
    public function isLongRunning(): bool { return false; }
}
```

- [ ] **Step 3: Implement Kernel**

```php
<?php
// packages/kernel/src/Kernel.php

namespace IndustrialProtocols;

use Erikwang2013\IndustrialProtocols\Config\ConfigRepositoryInterface;
use Erikwang2013\IndustrialProtocols\Config\FileConfigRepository;
use Erikwang2013\IndustrialProtocols\Connection\ConnectionManager;
use Erikwang2013\IndustrialProtocols\Connection\Strategy\LazyStrategy;
use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineFactory;
use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineAdapterInterface;
use Erikwang2013\IndustrialProtocols\Event\KernelBootedEvent;
use Erikwang2013\IndustrialProtocols\Framework\FrameworkAdapterInterface;
use Erikwang2013\IndustrialProtocols\Framework\PlainPhpAdapter;
use Erikwang2013\IndustrialProtocols\Log\LogDriverInterface;
use Erikwang2013\IndustrialProtocols\Log\PsrLogDriver;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

class Kernel
{
    private ProtocolRegistry $protocolRegistry;
    private ConnectionManager $connectionManager;
    private ConfigRepositoryInterface $configRepository;
    private CoroutineAdapterInterface $coroutine;
    private LogDriverInterface $log;
    private FrameworkAdapterInterface $framework;
    private bool $booted = false;

    public function __construct(
        private array $options = [],
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->protocolRegistry = new ProtocolRegistry();
        $this->coroutine = CoroutineFactory::create();
        $this->log = new PsrLogDriver(new NullLogger());
    }

    public function boot(): void
    {
        $configPath = $this->options['config_path']
            ?? dirname(__DIR__) . '/config/industrial-protocols.php';

        $this->configRepository = new FileConfigRepository($configPath);
        $this->framework = $this->detectFramework();
        $this->framework->registerConfig();
        $this->framework->registerServices();
        $this->framework->registerCommands();

        $this->connectionManager = new ConnectionManager(
            $this->protocolRegistry->all(),
            $this->configRepository,
            $this->eventDispatcher ?? new class implements EventDispatcherInterface {
                public function dispatch(object $event): object { return $event; }
            },
            $this->coroutine,
            $this->log,
            new LazyStrategy(),
        );

        $this->booted = true;

        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new KernelBootedEvent(
                array_keys($this->protocolRegistry->all()),
                $this->framework->getName(),
            ));
        }
    }

    public function shutdown(): void
    {
        $this->connectionManager?->shutdown();
        $this->booted = false;
    }

    public function getConnectionManager(): ConnectionManager
    {
        $this->ensureBooted();
        return $this->connectionManager;
    }

    public function getProtocolRegistry(): ProtocolRegistry
    {
        return $this->protocolRegistry;
    }

    public function getConfigRepository(): ConfigRepositoryInterface
    {
        $this->ensureBooted();
        return $this->configRepository;
    }

    public function getCoroutineAdapter(): CoroutineAdapterInterface
    {
        return $this->coroutine;
    }

    public function getLogDriver(): LogDriverInterface
    {
        return $this->log;
    }

    public function getFramework(): FrameworkAdapterInterface
    {
        return $this->framework;
    }

    private function detectFramework(): FrameworkAdapterInterface
    {
        $default = new PlainPhpAdapter(
            $this->options['config_path']
            ?? dirname(__DIR__) . '/config/industrial-protocols.php'
        );

        if ($default->detect()) return $default;

        return $default;
    }

    private function ensureBooted(): void
    {
        if (!$this->booted) {
            throw new \RuntimeException('Kernel must be booted before using. Call boot() first.');
        }
    }
}
```

- [ ] **Step 4: Write and run tests**

ProtocolRegistryTest: register/get/has/all/autoDiscover. KernelTest: boot creates components, registerProtocol, shutdown, invalid config throws.

Run: `vendor/bin/phpunit packages/kernel/tests/Unit/ProtocolRegistryTest.php packages/kernel/tests/Unit/KernelTest.php`
Expected: PASS (green)

- [ ] **Step 5: Commit**

```bash
git add packages/kernel/src/Protocol/ProtocolRegistry.php packages/kernel/src/Framework/ packages/kernel/src/Kernel.php packages/kernel/tests/Unit/ProtocolRegistryTest.php packages/kernel/tests/Unit/KernelTest.php
git commit -m "feat: add ProtocolRegistry, FrameworkAdapter, and Kernel"
```

---

### Task 13: Modbus Frame + Exception

**Files:**
- Create: `packages/modbus/src/Exception/ModbusException.php`
- Create: `packages/modbus/src/Frame/ModbusFrame.php`
- Create: `packages/modbus/src/Frame/ModbusRequest.php`
- Create: `packages/modbus/src/Frame/ModbusResponse.php`
- Test: `packages/modbus/tests/Unit/ModbusFrameTest.php`

- [ ] **Step 1: Implement ModbusException**

```php
<?php
// packages/modbus/src/Exception/ModbusException.php

namespace Erikwang2013\IndustrialProtocols\Modbus\Exception;

use Erikwang2013\IndustrialProtocols\Exception\ProtocolException;

class ModbusException extends ProtocolException
{
    public static function fromErrorCode(int $functionCode, int $exceptionCode): self
    {
        $messages = [
            0x01 => 'Illegal function',
            0x02 => 'Illegal data address',
            0x03 => 'Illegal data value',
            0x04 => 'Server device failure',
            0x06 => 'Server device busy',
        ];
        $msg = $messages[$exceptionCode] ?? "Unknown exception code: 0x" . dechex($exceptionCode);
        return new self("Modbus error (FC=0x" . dechex($functionCode) . "): $msg", [
            'function_code' => $functionCode,
            'exception_code' => $exceptionCode,
        ]);
    }
}
```

- [ ] **Step 2: Implement ModbusFrame (CRC16)**

```php
<?php
// packages/modbus/src/Frame/ModbusFrame.php

namespace Erikwang2013\IndustrialProtocols\Modbus\Frame;

abstract class ModbusFrame
{
    public static function crc16(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]);
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x0001) ? (($crc >> 1) ^ 0xA001) : ($crc >> 1);
            }
        }
        return $crc;
    }

    public static function appendCrc(string $data): string
    {
        $crc = self::crc16($data);
        return $data . chr($crc & 0xFF) . chr($crc >> 8);
    }

    public static function validateCrc(string $frame): bool
    {
        $len = strlen($frame);
        if ($len < 2) return false;
        $data = substr($frame, 0, $len - 2);
        $expected = self::crc16($data);
        $actual = ord($frame[$len - 1]) << 8 | ord($frame[$len - 2]);
        return $expected === $actual;
    }
}
```

- [ ] **Step 3: Implement ModbusRequest**

```php
<?php
// packages/modbus/src/Frame/ModbusRequest.php

namespace Erikwang2013\IndustrialProtocols\Modbus\Frame;

use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class ModbusRequest extends ModbusFrame implements FrameInterface
{
    private static int $transactionId = 1;

    private function __construct(
        private int $unitId,
        private int $functionCode,
        private string $pdu,
        private int $transactionId,
    ) {}

    public static function readHoldingRegisters(int $unitId, int $startAddr, int $quantity): self
    {
        $pdu = chr(0x03) . pack('n', $startAddr) . pack('n', $quantity);
        return new self($unitId, 0x03, $pdu, self::$transactionId++);
    }

    public static function readInputRegisters(int $unitId, int $startAddr, int $quantity): self
    {
        $pdu = chr(0x04) . pack('n', $startAddr) . pack('n', $quantity);
        return new self($unitId, 0x04, $pdu, self::$transactionId++);
    }

    public static function readCoils(int $unitId, int $startAddr, int $quantity): self
    {
        $pdu = chr(0x01) . pack('n', $startAddr) . pack('n', $quantity);
        return new self($unitId, 0x01, $pdu, self::$transactionId++);
    }

    public static function writeSingleRegister(int $unitId, int $address, int $value): self
    {
        $pdu = chr(0x06) . pack('n', $address) . pack('n', $value);
        return new self($unitId, 0x06, $pdu, self::$transactionId++);
    }

    public static function writeMultipleRegisters(int $unitId, int $startAddr, array $values): self
    {
        $count = count($values);
        $byteCount = $count * 2;
        $pdu = chr(0x10) . pack('n', $startAddr) . pack('n', $count) . chr($byteCount);
        foreach ($values as $val) { $pdu .= pack('n', $val); }
        return new self($unitId, 0x10, $pdu, self::$transactionId++);
    }

    public function toBytes(): string
    {
        $length = strlen($this->pdu) + 1;
        return pack('n', $this->transactionId) . pack('n', 0) . pack('n', $length)
            . chr($this->unitId) . $this->pdu;
    }

    public static function fromBytes(string $bytes): static
    {
        throw new \BadMethodCallException('Cannot build request from bytes');
    }

    public function getData(): array
    {
        return [
            'unit_id' => $this->unitId,
            'function_code' => $this->functionCode,
            'transaction_id' => $this->transactionId,
        ];
    }
}
```

- [ ] **Step 4: Implement ModbusResponse**

```php
<?php
// packages/modbus/src/Frame/ModbusResponse.php

namespace Erikwang2013\IndustrialProtocols\Modbus\Frame;

use Erikwang2013\IndustrialProtocols\Modbus\Exception\ModbusException;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class ModbusResponse extends ModbusFrame implements FrameInterface
{
    private int $transactionId;
    private int $unitId;
    private int $functionCode;
    private string $data;

    private function __construct() {}

    public static function fromBytes(string $bytes): static
    {
        if (strlen($bytes) < 9) {
            throw new ModbusException('Response too short: ' . strlen($bytes) . ' bytes');
        }

        $r = new self();
        $r->transactionId = unpack('n', substr($bytes, 0, 2))[1];
        $length = unpack('n', substr($bytes, 4, 2))[1];
        $r->unitId = ord($bytes[6]);
        $r->functionCode = ord($bytes[7]);

        if ($r->functionCode & 0x80) {
            throw ModbusException::fromErrorCode($r->functionCode & 0x7F, ord($bytes[8]));
        }

        $r->data = substr($bytes, 8, $length - 2);
        return $r;
    }

    public function toBytes(): string
    {
        throw new \BadMethodCallException('Response is parsed from bytes');
    }

    public function getData(): array
    {
        return ['bytes' => array_values(unpack('C*', $this->data))];
    }

    public function getRegisters(): array
    {
        $byteCount = ord($this->data[0]);
        $registerBytes = substr($this->data, 1, $byteCount);
        $registers = [];
        for ($i = 0; $i < $byteCount / 2; $i++) {
            $registers[] = unpack('n', substr($registerBytes, $i * 2, 2))[1];
        }
        return $registers;
    }

    public function getTransactionId(): int { return $this->transactionId; }
    public function getUnitId(): int { return $this->unitId; }
    public function getFunctionCode(): int { return $this->functionCode; }
}
```

- [ ] **Step 5: Write and run test**

Test covers: build read holding registers, write single register, write multiple registers, parse response, detect exception response (0x80 + error code), CRC16 calculation, CRC validation.

Run: `vendor/bin/phpunit packages/modbus/tests/Unit/ModbusFrameTest.php`
Expected: PASS (green)

- [ ] **Step 6: Commit**

```bash
git add packages/modbus/
git commit -m "feat: add Modbus frame encode/decode with CRC16"
```

---

### Task 14: Modbus TCP Driver + Protocol + Connector

**Files:**
- Create: `packages/modbus/src/Driver/ModbusTcpDriver.php`
- Create: `packages/modbus/src/ModbusProtocol.php`
- Create: `packages/modbus/src/ModbusConnector.php`
- Test: `packages/modbus/tests/Simulation/ModbusConnectorTest.php`

- [ ] **Step 1: Implement ModbusTcpDriver**

```php
<?php
// packages/modbus/src/Driver/ModbusTcpDriver.php

namespace Erikwang2013\IndustrialProtocols\Modbus\Driver;

use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;
use Erikwang2013\IndustrialProtocols\Modbus\Exception\ModbusException;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

class ModbusTcpDriver implements DriverInterface
{
    private $socket = null;
    private float $lastLatency = 0.0;

    public function __construct(
        private string $host,
        private int $port,
        private float $timeout = 3.0,
    ) {}

    public function connect(): void
    {
        $this->socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno, $errstr, $this->timeout,
        );
        if (!$this->socket) {
            throw new ConnectionTimeoutException(
                "Failed to connect to {$this->host}:{$this->port}: [$errno] $errstr",
                ['host' => $this->host, 'port' => $this->port],
            );
        }
        stream_set_timeout($this->socket, (int)$this->timeout, (int)(($this->timeout - (int)$this->timeout) * 1e6));
    }

    public function disconnect(): void
    {
        if ($this->socket) { fclose($this->socket); $this->socket = null; }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$this->socket) throw new ModbusException('Not connected');

        $requestBytes = $frame->toBytes();
        $start = microtime(true);
        fwrite($this->socket, $requestBytes);

        $header = @fread($this->socket, 7);
        if ($header === false || strlen($header) < 7) {
            if (stream_get_meta_data($this->socket)['timed_out']) {
                throw new ConnectionTimeoutException('Read timeout');
            }
            throw new ModbusException('Failed to read response header');
        }

        $length = unpack('n', substr($header, 4, 2))[1];
        $remaining = @fread($this->socket, $length - 1);
        if ($remaining === false) throw new ModbusException('Failed to read response body');

        $this->lastLatency = (microtime(true) - $start) * 1000;
        return $frame::fromBytes($header . $remaining);
    }

    public function sendAsync(FrameInterface $frame): mixed { return $this->send($frame); }
    public function getLatency(): float { return $this->lastLatency; }
    public function supportsAsync(): bool { return false; }
}
```

- [ ] **Step 2: Implement ModbusProtocol**

```php
<?php
// packages/modbus/src/ModbusProtocol.php

namespace Erikwang2013\IndustrialProtocols\Modbus;

use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolInterface;

class ModbusProtocol implements ProtocolInterface
{
    public function getName(): string { return 'modbus'; }
    public function getVersion(): string { return '1.0.0'; }
    public function getSupportedVariants(): array { return ['tcp', 'rtu', 'ascii']; }
    public function getDefaultPort(): int { return 502; }

    public function createConnector(array $config): ConnectorInterface
    {
        return new ModbusConnector($config);
    }
}
```

- [ ] **Step 3: Implement ModbusConnector**

```php
<?php
// packages/modbus/src/ModbusConnector.php

namespace Erikwang2013\IndustrialProtocols\Modbus;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Exception\AddressOutOfRangeException;
use Erikwang2013\IndustrialProtocols\Modbus\Driver\ModbusTcpDriver;
use Erikwang2013\IndustrialProtocols\Modbus\Frame\ModbusRequest;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class ModbusConnector implements ConnectorInterface
{
    private ModbusTcpDriver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new ModbusTcpDriver(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 502,
            ($config['timeout'] ?? 3000) / 1000.0,
        );
    }

    public function connect(): void { $this->driver->connect(); }
    public function disconnect(): void { $this->driver->disconnect(); }
    public function isConnected(): bool { return $this->driver->isConnected(); }

    public function read(string|array $points): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $address) {
            $regAddr = $this->parseAddress($address);
            $request = ModbusRequest::readHoldingRegisters(
                $this->config['unit_id'] ?? 1, $regAddr, 1,
            );
            $response = $this->driver->send($request);
            $registers = $response->getRegisters();
            $results[$address] = $registers[0] ?? null;
        }
        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $i => $address) {
            $value = is_array($values) ? ($values[$address] ?? $values[$i] ?? null) : $values;
            $regAddr = $this->parseAddress($address);
            $request = ModbusRequest::writeSingleRegister(
                $this->config['unit_id'] ?? 1, $regAddr, (int)$value,
            );
            $this->driver->send($request);
            $results[$address] = $value;
        }
        return $results;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) return HealthStatus::closed('Not connected');
        return HealthStatus::healthy($this->driver->getLatency());
    }

    private function parseAddress(string $address): int
    {
        $addr = (int)$address;
        if ($addr >= 40001 && $addr <= 49999) return $addr - 40001;
        if ($addr >= 30001 && $addr <= 39999) return $addr - 30001;
        if ($addr >= 0 && $addr <= 9999) return $addr;
        throw new AddressOutOfRangeException("Invalid Modbus address: $address");
    }
}
```

- [ ] **Step 4: Write and run simulation test**

Test uses `stream_socket_server` + `pcntl_fork` to create real TCP mock server. Tests: read holding register, write single register, timeout detection, protocol factory creates correct connector, health status.

Run: `vendor/bin/phpunit packages/modbus/tests/Simulation/ModbusConnectorTest.php`
Expected: PASS (green)

- [ ] **Step 5: Commit**

```bash
git add packages/modbus/
git commit -m "feat: add Modbus TCP driver, protocol, and connector"
```

---

### Task 15: End-to-End Integration Test + Documentation

**Files:**
- Create: `tests/Integration/KernelModbusIntegrationTest.php`
- Create: `README.md`

- [ ] **Step 1: Write integration test**

Test: Kernel boot → register ModbusProtocol → connect to mock TCP server → read 40001 → verify value → health check → shutdown. Also tests PlainPhpAdapter usage pattern.

```php
<?php
// tests/Integration/KernelModbusIntegrationTest.php

namespace Erikwang2013\IndustrialProtocols\Tests\Integration;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;
use PHPUnit\Framework\TestCase;

class KernelModbusIntegrationTest extends TestCase
{
    public function testFullFlowKernelWithModbus(): void
    {
        $configPath = sys_get_temp_dir() . '/integration-' . uniqid() . '.php';
        file_put_contents($configPath, '<?php return ' . var_export([
            'devices' => [
                'test-plc' => [
                    'protocol' => 'modbus', 'variant' => 'tcp',
                    'host' => '127.0.0.1', 'port' => 15030,
                    'unit_id' => 1, 'timeout' => 2000,
                ],
            ],
            'gateway' => ['rules' => []],
            'health_check_interval' => 30,
        ], true) . ';');

        $server = stream_socket_server('tcp://127.0.0.1:15030');
        $pid = pcntl_fork();
        if ($pid === 0) {
            $client = @stream_socket_accept($server, 2);
            if ($client) {
                fread($client, 256);
                fwrite($client, hex2bin('000100000005010302002A'));
                fclose($client);
            }
            fclose($server);
            exit(0);
        }
        usleep(50000);

        try {
            $kernel = new Kernel(['config_path' => $configPath]);
            $kernel->getProtocolRegistry()->register(new ModbusProtocol());
            $kernel->boot();

            $conn = $kernel->getConnectionManager()->connect('test-plc');
            $this->assertTrue($conn->isConnected());

            $result = $conn->read('40001');
            $this->assertSame(42, $result['40001']);

            $health = $kernel->getConnectionManager()->health('test-plc');
            $this->assertSame(ConnectionState::HEALTHY, $health->state);

            $kernel->shutdown();
        } finally {
            fclose($server);
            pcntl_waitpid($pid, $status);
            if (file_exists($configPath)) unlink($configPath);
        }
    }
}
```

- [ ] **Step 2: Run integration test**

Run: `vendor/bin/phpunit tests/Integration/KernelModbusIntegrationTest.php`
Expected: PASS (green)

- [ ] **Step 3: Run full test suite with coverage**

Run: `vendor/bin/phpunit --coverage-text`
Expected: All tests pass, coverage ≥80% for kernel and modbus packages

- [ ] **Step 4: Write README.md** with project overview, supported protocols, framework support, quick start example, configuration reference

- [ ] **Step 5: Final commit**

```bash
git add tests/Integration/ README.md
git commit -m "test: add kernel + modbus end-to-end integration tests and README"
```

---

### Post-Phase-1 Verification Checklist

- [ ] `vendor/bin/phpunit` — all tests pass
- [ ] `vendor/bin/phpunit --coverage-text` — ≥80% coverage on kernel + modbus
- [ ] `php -r "require 'vendor/autoload.php'; (new Erikwang2013\IndustrialProtocols\Kernel)->boot();"` — kernel boots without error
- [ ] `composer validate` — all composer.json files valid
- [ ] Modbus protocol auto-discovery via `extra.industrial-protocols.protocol` works
- [ ] PlainPhpAdapter serves as fallback when no framework detected
