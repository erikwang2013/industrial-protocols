<?php

namespace IndustrialProtocols\Tests\Simulation;

use IndustrialProtocols\Connection\ConnectionManager;
use IndustrialProtocols\Connection\Strategy\LazyStrategy;
use IndustrialProtocols\Config\ConfigRepositoryInterface;
use IndustrialProtocols\Coroutine\SyncCoroutineAdapter;
use IndustrialProtocols\Gateway\GatewayEngine;
use IndustrialProtocols\Gateway\GatewayRule;
use IndustrialProtocols\Log\NullLogDriver;
use IndustrialProtocols\Protocol\ConnectorInterface;
use IndustrialProtocols\Protocol\ProtocolInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

class GatewayEngineTest extends TestCase
{
    public function testAddAndExecuteRule(): void
    {
        // Setup mock connectors
        $sourceConnector = $this->createMock(ConnectorInterface::class);
        $sourceConnector->method('read')->willReturn(['40001' => 42]);
        $sourceConnector->method('isConnected')->willReturn(true);

        $targetConnector = $this->createMock(ConnectorInterface::class);
        $targetConnector->method('write')->willReturn(['ns=1;s=Temp' => 42]);
        $targetConnector->method('isConnected')->willReturn(true);

        // Setup protocol registry with device connections
        $mockProtocol = $this->createMock(ProtocolInterface::class);
        $mockProtocol->method('getName')->willReturn('mock');
        $mockProtocol->method('createConnector')->willReturnOnConsecutiveCalls($sourceConnector, $targetConnector);

        $configRepo = $this->createMock(ConfigRepositoryInterface::class);
        $configRepo->method('getDeviceConfig')->willReturnCallback(function ($deviceId) {
            return ['protocol' => 'mock', 'host' => '127.0.0.1', 'port' => 9999, 'timeout' => 1000];
        });

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $connectionManager = new ConnectionManager(
            ['mock' => $mockProtocol],
            $configRepo,
            $eventDispatcher,
            new SyncCoroutineAdapter(),
            new NullLogDriver(),
            new LazyStrategy(),
        );

        $engine = new GatewayEngine(
            $connectionManager, $eventDispatcher,
            new SyncCoroutineAdapter(), new NullLogDriver(),
        );

        $rule = new GatewayRule(
            id: 'gw-001',
            sourceDevice: 'plc-001',
            sourcePoint: '40001',
            targetDevice: 'opcua-server',
            targetPoint: 'ns=1;s=Temp',
        );

        $engine->addRule($rule);
        $this->assertCount(1, $engine->getRules());

        $result = $engine->executeOnce('gw-001');
        $this->assertSame('ok', $result['status']);
        $this->assertSame(42, $result['value']);
    }

    public function testCircuitBreakerOpensAfterFailures(): void
    {
        $sourceConnector = $this->createMock(ConnectorInterface::class);
        $sourceConnector->method('read')->willThrowException(new \RuntimeException('Device offline'));
        $sourceConnector->method('isConnected')->willReturn(true);

        $targetConnector = $this->createMock(ConnectorInterface::class);
        $targetConnector->method('isConnected')->willReturn(true);

        $mockProtocol = $this->createMock(ProtocolInterface::class);
        $mockProtocol->method('getName')->willReturn('mock');
        $mockProtocol->method('createConnector')->willReturnOnConsecutiveCalls($sourceConnector, $targetConnector);

        $configRepo = $this->createMock(ConfigRepositoryInterface::class);
        $configRepo->method('getDeviceConfig')->willReturn(['protocol' => 'mock', 'host' => '127.0.0.1', 'port' => 9999, 'timeout' => 1000]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $connectionManager = new ConnectionManager(
            ['mock' => $mockProtocol],
            $configRepo,
            $eventDispatcher,
            new SyncCoroutineAdapter(),
            new NullLogDriver(),
            new LazyStrategy(),
        );

        $engine = new GatewayEngine(
            $connectionManager, $eventDispatcher,
            new SyncCoroutineAdapter(), new NullLogDriver(),
        );

        $rule = new GatewayRule(
            id: 'gw-001',
            sourceDevice: 'plc-001',
            sourcePoint: '40001',
            targetDevice: 'opcua-server',
            targetPoint: 'ns=1;s=Temp',
            failureThreshold: 2,
        );

        $engine->addRule($rule);

        // First failure
        $r1 = $engine->executeOnce('gw-001');
        $this->assertSame('error', $r1['status']);

        // Second failure — should trip circuit breaker
        $r2 = $engine->executeOnce('gw-001');
        $this->assertSame('error', $r2['status']);

        // Third attempt — breaker is open
        $this->expectException(\RuntimeException::class);
        $engine->executeOnce('gw-001');
    }

    public function testTransformFunction(): void
    {
        $sourceConnector = $this->createMock(ConnectorInterface::class);
        $sourceConnector->method('read')->willReturn(['40001' => 100]);
        $sourceConnector->method('isConnected')->willReturn(true);

        $targetConnector = $this->createMock(ConnectorInterface::class);
        $targetConnector->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(function ($data) {
                    return isset($data['ns=1;s=Scaled']) && $data['ns=1;s=Scaled'] === 50;
                }),
                $this->anything()
            );
        $targetConnector->method('isConnected')->willReturn(true);

        $mockProtocol = $this->createMock(ProtocolInterface::class);
        $mockProtocol->method('getName')->willReturn('mock');
        $mockProtocol->method('createConnector')->willReturnOnConsecutiveCalls($sourceConnector, $targetConnector);

        $configRepo = $this->createMock(ConfigRepositoryInterface::class);
        $configRepo->method('getDeviceConfig')->willReturn(['protocol' => 'mock', 'host' => '127.0.0.1', 'port' => 9999, 'timeout' => 1000]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $connectionManager = new ConnectionManager(
            ['mock' => $mockProtocol],
            $configRepo,
            $eventDispatcher,
            new SyncCoroutineAdapter(),
            new NullLogDriver(),
            new LazyStrategy(),
        );

        $engine = new GatewayEngine(
            $connectionManager, $eventDispatcher,
            new SyncCoroutineAdapter(), new NullLogDriver(),
        );

        $rule = new GatewayRule(
            id: 'gw-scaled',
            sourceDevice: 'plc-001',
            sourcePoint: '40001',
            targetDevice: 'opcua-server',
            targetPoint: 'ns=1;s=Scaled',
            transform: fn($v) => $v / 2,
        );

        $engine->addRule($rule);
        $result = $engine->executeOnce('gw-scaled');
        $this->assertSame('ok', $result['status']);
        $this->assertSame(50, $result['value']);
    }
}
