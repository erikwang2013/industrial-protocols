<?php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Connection\Strategy\PooledStrategy;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use PHPUnit\Framework\TestCase;

class PooledStrategyTest extends TestCase
{
    public function testPooledStrategyCreatesPool(): void
    {
        $connectors = [];
        $factoryCount = 0;

        $factory = function () use (&$connectors, &$factoryCount) {
            $factoryCount++;
            $connector = $this->createMock(ConnectorInterface::class);
            $connector->method('connect');
            $connectors[] = $connector;
            return $connector;
        };

        $strategy = new PooledStrategy(3);
        $result = $strategy->getOrCreate('dev-1', $factory);

        $this->assertInstanceOf(ConnectorInterface::class, $result);
        $this->assertSame(3, $factoryCount); // All 3 pre-created
        $this->assertCount(1, $strategy->getActiveConnections());
    }

    public function testPooledStrategyRoundRobin(): void
    {
        $index = 0;
        $connectors = [];
        for ($i = 0; $i < 3; $i++) {
            $connectors[$i] = $this->createMock(ConnectorInterface::class);
            $connectors[$i]->method('connect');
        }

        $strategy = new PooledStrategy(2);
        $c1 = $strategy->getOrCreate('dev-1', function () use ($connectors, &$index) {
            return $connectors[$index++];
        });
        $c2 = $strategy->getOrCreate('dev-1', function () use ($connectors, &$index) {
            return $connectors[$index++];
        });
        $c3 = $strategy->getOrCreate('dev-1', function () use ($connectors, &$index) {
            return $connectors[$index++];
        });

        // Round-robin: c1!=c2, c3 wraps back
        $this->assertSame($connectors[0], $c1);
        $this->assertSame($connectors[1], $c2);
        $this->assertSame($connectors[0], $c3);
    }

    public function testPooledStrategyDisconnectAll(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->exactly(2))->method('disconnect');
        $connector->method('connect');

        $idx = 0;
        $strategy = new PooledStrategy(2);
        $strategy->getOrCreate('dev-1', function () use ($connector, &$idx) {
            $idx++;
            return $connector;
        });

        $strategy->disconnectAll();
        $this->assertEmpty($strategy->getActiveConnections());
    }
}
