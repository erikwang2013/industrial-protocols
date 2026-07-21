<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Gateway\CircuitBreaker;
use PHPUnit\Framework\TestCase;

class CircuitBreakerTest extends TestCase
{
    public function testInitialStateIsClosed(): void
    {
        $cb = new CircuitBreaker('test', 3, 30);
        $this->assertSame('CLOSED', $cb->getState());
        $this->assertFalse($cb->isOpen());
    }

    public function testOpensAfterThresholdFailures(): void
    {
        $cb = new CircuitBreaker('test', 3, 30);
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame('CLOSED', $cb->getState());

        $cb->recordFailure(); // 3rd → trip
        $this->assertTrue($cb->isOpen());
        $this->assertSame('OPEN', $cb->getState());
    }

    public function testSuccessResetsFailureCount(): void
    {
        $cb = new CircuitBreaker('test', 5, 30);
        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordSuccess();
        $this->assertSame(0, $cb->getFailureCount());
        $this->assertSame('CLOSED', $cb->getState());
    }

    public function testCooldownTransitionsToHalfOpen(): void
    {
        $cb = new CircuitBreaker('test', 2, 0.01); // 10ms cooldown
        $cb->recordFailure();
        $cb->recordFailure(); // opens
        $this->assertTrue($cb->isOpen());

        usleep(20000); // 20ms > cooldown
        $this->assertFalse($cb->isOpen()); // now half-open
    }

    public function testReset(): void
    {
        $cb = new CircuitBreaker('test', 2, 30);
        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertTrue($cb->isOpen());

        $cb->reset();
        $this->assertSame('CLOSED', $cb->getState());
        $this->assertSame(0, $cb->getFailureCount());
    }
}
