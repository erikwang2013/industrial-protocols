<?php

namespace IndustrialProtocols\Tests\Simulation;

use IndustrialProtocols\Config\ConfigRepositoryInterface;
use IndustrialProtocols\Connection\ConnectionManager;
use IndustrialProtocols\Connection\ConnectionState;
use IndustrialProtocols\Connection\HealthStatus;
use IndustrialProtocols\Connection\Strategy\LazyStrategy;
use IndustrialProtocols\Coroutine\SyncCoroutineAdapter;
use IndustrialProtocols\Log\NullLogDriver;
use IndustrialProtocols\Protocol\ConnectorInterface;
use IndustrialProtocols\Protocol\ProtocolInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

class ConnectionManagerTest extends TestCase
{
    public function testConnectLazyCreatesConnectionOnFirstAccess(): void
    {
        $protocol = $this->createMockProtocol();
        $configRepo = $this->createMockConfigRepo();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = new ConnectionManager(
            ['mock' => $protocol],
            $configRepo,
            $eventDispatcher,
            new SyncCoroutineAdapter(),
            new NullLogDriver(),
            new LazyStrategy(),
        );

        $this->assertNull($manager->getConnection('test-device'));

        $conn = $manager->connect('test-device');
        $this->assertTrue($conn->isConnected());
        $this->assertSame($conn, $manager->getConnection('test-device'));
    }

    public function testDisconnectRemovesConnection(): void
    {
        $protocol = $this->createMockProtocol();
        $configRepo = $this->createMockConfigRepo();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = new ConnectionManager(
            ['mock' => $protocol],
            $configRepo,
            $eventDispatcher,
            new SyncCoroutineAdapter(),
            new NullLogDriver(),
            new LazyStrategy(),
        );

        $manager->connect('test-device');
        $manager->disconnect('test-device');
        $this->assertNull($manager->getConnection('test-device'));
    }

    public function testHealthReturnsStatus(): void
    {
        $protocol = $this->createMockProtocol();
        $configRepo = $this->createMockConfigRepo();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = new ConnectionManager(
            ['mock' => $protocol],
            $configRepo,
            $eventDispatcher,
            new SyncCoroutineAdapter(),
            new NullLogDriver(),
            new LazyStrategy(),
        );

        $manager->connect('test-device');
        $status = $manager->health('test-device');
        $this->assertInstanceOf(HealthStatus::class, $status);
    }

    public function testConnectThrowsIfDeviceNotFound(): void
    {
        $protocol = $this->createMockProtocol();
        $configRepo = $this->createMock(ConfigRepositoryInterface::class);
        $configRepo->method('getDeviceConfig')->willThrowException(new \RuntimeException('not found'));
        $configRepo->method('getAllDeviceConfigs')->willReturn([]);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = new ConnectionManager(
            ['mock' => $protocol],
            $configRepo,
            $eventDispatcher,
            new SyncCoroutineAdapter(),
            new NullLogDriver(),
            new LazyStrategy(),
        );

        $this->expectException(\RuntimeException::class);
        $manager->connect('non-existent');
    }

    private function createMockProtocol(): ProtocolInterface
    {
        $protocol = $this->createMock(ProtocolInterface::class);
        $protocol->method('getName')->willReturn('mock');
        $protocol->method('createConnector')->willReturnCallback(function () {
            $connector = $this->createMock(ConnectorInterface::class);
            $connected = false;
            $connector->method('connect')->willReturnCallback(function () use (&$connected) { $connected = true; });
            $connector->method('disconnect')->willReturnCallback(function () use (&$connected) { $connected = false; });
            $connector->method('isConnected')->willReturnCallback(function () use (&$connected) { return $connected; });
            $connector->method('getHealth')->willReturn(HealthStatus::healthy(1.0));
            $connector->method('read')->willReturn(['40001' => 42]);
            $connector->method('write')->willReturn(['40001' => 42]);
            return $connector;
        });
        return $protocol;
    }

    private function createMockConfigRepo(): ConfigRepositoryInterface
    {
        $repo = $this->createMock(ConfigRepositoryInterface::class);
        $repo->method('getDeviceConfig')->willReturn([
            'protocol' => 'mock',
            'host'     => '127.0.0.1',
            'port'     => 9999,
            'timeout'  => 1000,
        ]);
        $repo->method('getAllDeviceConfigs')->willReturn([
            'test-device' => ['protocol' => 'mock', 'host' => '127.0.0.1', 'port' => 9999],
        ]);
        return $repo;
    }
}
