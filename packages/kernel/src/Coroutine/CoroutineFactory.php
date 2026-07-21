<?php

namespace IndustrialProtocols\Coroutine;

class CoroutineFactory
{
    private static array $adapters = [
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
