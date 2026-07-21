<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Coroutine\SwooleCoroutineAdapter;
use PHPUnit\Framework\TestCase;

class SwooleAdapterTest extends TestCase
{
    public function testSwooleAdapterName(): void
    {
        $adapter = new SwooleCoroutineAdapter();
        $this->assertSame('swoole', $adapter->getName());
    }

    public function testSwooleAdapterAvailabilityCheck(): void
    {
        $adapter = new SwooleCoroutineAdapter();
        // Without swoole extension loaded, returns false
        $available = $adapter->isAvailable();
        $this->assertIsBool($available);
    }
}
