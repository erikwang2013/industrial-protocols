<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineAdapterInterface;
use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineFactory;
use Erikwang2013\IndustrialProtocols\Coroutine\SyncCoroutineAdapter;
use PHPUnit\Framework\TestCase;

class CoroutineAdapterTest extends TestCase
{
    public function testSyncAdapterIsAlwaysAvailable(): void
    {
        $adapter = new SyncCoroutineAdapter();
        $this->assertTrue($adapter->isAvailable());
        $this->assertSame('sync', $adapter->getName());
    }

    public function testSyncAdapterCreateRunsSync(): void
    {
        $adapter = new SyncCoroutineAdapter();
        $result = $adapter->create(fn() => 42);
        $this->assertSame(42, $result);
    }

    public function testSyncAdapterSleepDoesNotThrow(): void
    {
        $adapter = new SyncCoroutineAdapter();
        $start = microtime(true);
        $adapter->sleep(0.01);
        $elapsed = microtime(true) - $start;
        $this->assertGreaterThanOrEqual(0.01, $elapsed);
    }

    public function testSyncAdapterParallelRunsSequentially(): void
    {
        $adapter = new SyncCoroutineAdapter();
        $order = [];
        $results = $adapter->parallel([
            function () use (&$order) { $order[] = 1; return 'a'; },
            function () use (&$order) { $order[] = 2; return 'b'; },
        ]);
        $this->assertSame([1, 2], $order);
        $this->assertSame(['a', 'b'], $results);
    }

    public function testCoroutineFactoryReturnsAdapter(): void
    {
        $adapter = CoroutineFactory::create();
        $this->assertInstanceOf(CoroutineAdapterInterface::class, $adapter);
    }
}
