<?php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Coroutine\SwooleCoroutineAdapter;
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
