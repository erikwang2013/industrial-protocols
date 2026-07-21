<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Exception\ConnectionTimeoutException;
use Erikwang2013\IndustrialProtocols\Exception\AddressOutOfRangeException;
use Erikwang2013\IndustrialProtocols\Retry\ExponentialBackoffStrategy;
use Erikwang2013\IndustrialProtocols\Retry\FixedRetryStrategy;
use Erikwang2013\IndustrialProtocols\Retry\NoRetryStrategy;
use PHPUnit\Framework\TestCase;

class RetryStrategyTest extends TestCase
{
    public function testNoRetryNeverRetries(): void
    {
        $s = new NoRetryStrategy();
        $this->assertFalse($s->shouldRetry(1, new ConnectionTimeoutException('test')));
        $this->assertSame(0, $s->getDelay(1));
    }

    public function testFixedRetryWithMaxAttempts(): void
    {
        $s = new FixedRetryStrategy(3, 1000);
        $this->assertTrue($s->shouldRetry(1, new ConnectionTimeoutException('test')));
        $this->assertTrue($s->shouldRetry(2, new ConnectionTimeoutException('test')));
        $this->assertTrue($s->shouldRetry(3, new ConnectionTimeoutException('test')));
        $this->assertFalse($s->shouldRetry(4, new ConnectionTimeoutException('test')));
        $this->assertSame(1000, $s->getDelay(3));
    }

    public function testExponentialBackoff(): void
    {
        $s = new ExponentialBackoffStrategy(3);
        $this->assertTrue($s->shouldRetry(1, new ConnectionTimeoutException('test')));
        $this->assertFalse($s->shouldRetry(4, new ConnectionTimeoutException('test')));
        $this->assertSame(1000, $s->getDelay(1));
        $this->assertSame(2000, $s->getDelay(2));
        $this->assertSame(4000, $s->getDelay(3));
    }

    public function testExponentialBackoffWithJitter(): void
    {
        $s = new ExponentialBackoffStrategy(3, 1000, true);
        $delay = $s->getDelay(2);
        $this->assertGreaterThanOrEqual(1000, $delay);
        $this->assertLessThanOrEqual(3000, $delay);
    }

    public function testFixedRetryOnlyForRetryableExceptions(): void
    {
        $s = new FixedRetryStrategy(3, 1000, [ConnectionTimeoutException::class]);
        $this->assertTrue($s->shouldRetry(1, new ConnectionTimeoutException('test')));
        $this->assertFalse($s->shouldRetry(1, new AddressOutOfRangeException('test')));
    }
}
