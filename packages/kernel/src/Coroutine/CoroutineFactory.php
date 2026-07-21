<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Coroutine;

class CoroutineFactory
{
    private static array $adapters = [
        SwooleCoroutineAdapter::class,
        FiberCoroutineAdapter::class,
        SyncCoroutineAdapter::class,
    ];

    public static function create(): CoroutineAdapterInterface
    {
        foreach (self::$adapters as $class) {
            $adapter = new $class();
            if ($adapter->isAvailable()) { return $adapter; }
        }
        return new SyncCoroutineAdapter();
    }
}
