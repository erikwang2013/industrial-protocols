<?php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Connection\Strategy\EagerStrategy;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use PHPUnit\Framework\TestCase;

class EagerStrategyTest extends TestCase
{
    public function testEagerStrategyCallsConnectImmediately(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())->method('connect');

        $strategy = new EagerStrategy();
        $result = $strategy->getOrCreate('dev-1', function () use ($connector) {
            return $connector;
        });

        $this->assertSame($connector, $result);
    }

    public function testEagerStrategyReusesExistingConnection(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())->method('connect');

        $strategy = new EagerStrategy();
        $strategy->getOrCreate('dev-1', fn() => $connector);

        $connector2 = $this->createMock(ConnectorInterface::class);
        $connector2->expects($this->never())->method('connect');

        $result = $strategy->getOrCreate('dev-1', fn() => $connector2);
        $this->assertSame($connector, $result);
    }

    public function testEagerStrategyDisconnect(): void
    {
        $connector = $this->createMock(ConnectorInterface::class);
        $connector->expects($this->once())->method('disconnect');

        $strategy = new EagerStrategy();
        $strategy->getOrCreate('dev-1', fn() => $connector);
        $strategy->disconnect('dev-1');

        $this->assertEmpty($strategy->getActiveConnections());
    }
}
