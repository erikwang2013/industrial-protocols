<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Coroutine;

class SyncCoroutineAdapter implements CoroutineAdapterInterface
{
    public function isAvailable(): bool { return true; }
    public function getName(): string { return 'sync'; }
    public function create(callable $fn): mixed { return $fn(); }
    public function sleep(float $seconds): void { usleep((int)($seconds * 1_000_000)); }
    public function parallel(array $callables): array
    {
        $results = [];
        foreach ($callables as $callable) { $results[] = $callable(); }
        return $results;
    }
}
