<?php

namespace IndustrialProtocols\Tests\Integration;

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

class GatewayIntegrationTest extends TestCase
{
    public function testGatewayPollTriggersMultipleTransfers(): void
    {
        $sourceConnector = $this->createMock(ConnectorInterface::class);
        $sourceConnector->method('read')->willReturnCallback(function ($points) {
            if (is_array($points)) {
                $result = [];
                foreach ($points as $p) { $result[$p] = ord($p[0]); }
                return $result;
            }
            return [$points => ord($points[0])];
        });
        $sourceConnector->method('isConnected')->willReturn(true);

        $targetConnector = $this->createMock(ConnectorInterface::class);
        $targetConnector->method('write')->willReturnCallback(fn($p, $v) => [$p[0] ?? array_key_first($p) => array_values($v)[0] ?? 0]);
        $targetConnector->method('isConnected')->willReturn(true);

        $mockProtocol = $this->createMock(ProtocolInterface::class);
        $mockProtocol->method('getName')->willReturn('mock');
        $mockProtocol->method('createConnector')->willReturnOnConsecutiveCalls($sourceConnector, $targetConnector);

        $configRepo = $this->createMock(ConfigRepositoryInterface::class);
        $configRepo->method('getDeviceConfig')->willReturnCallback(function ($id) {
            return ['protocol' => 'mock', 'host' => '127.0.0.1', 'port' => ($id === 'src') ? 15001 : 15002, 'timeout' => 1000];
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

        // Add 2 rules
        $engine->addRule(new GatewayRule('gw-1', 'src', 'A', 'tgt', 'X'));
        $engine->addRule(new GatewayRule('gw-2', 'src', 'B', 'tgt', 'Y'));

        $results = $engine->tick();
        $this->assertCount(2, $results);
        $this->assertSame('ok', $results['gw-1']['status']);
        $this->assertSame('ok', $results['gw-2']['status']);
    }

    public function testGatewayRuleWithTransform(): void
    {
        $sourceConnector = $this->createMock(ConnectorInterface::class);
        $sourceConnector->method('read')->willReturn(['40001' => 100]);
        $sourceConnector->method('isConnected')->willReturn(true);

        $targetConnector = $this->createMock(ConnectorInterface::class);
        $targetConnector->expects($this->once())
            ->method('write')
            ->with(
                $this->callback(fn($data) => reset($data) == 212),
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

        $engine->addRule(new GatewayRule(
            id: 'gw-celsius',
            sourceDevice: 'plc-001',
            sourcePoint: '40001',
            targetDevice: 'opcua-server',
            targetPoint: 'ns=1;s=Fahrenheit',
            transform: fn($c) => $c * 9 / 5 + 32, // Celsius -> Fahrenheit
        ));

        $result = $engine->executeOnce('gw-celsius');
        $this->assertSame('ok', $result['status']);
        $this->assertSame(212, $result['value']);
    }
}
