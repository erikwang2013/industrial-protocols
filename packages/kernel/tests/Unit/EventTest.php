<?php
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
