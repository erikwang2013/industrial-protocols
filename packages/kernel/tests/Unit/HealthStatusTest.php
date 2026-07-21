<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Connection\ConnectionState;
use IndustrialProtocols\Connection\HealthStatus;
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
