<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Coroutine;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;

class SwooleCoroutineAdapter implements CoroutineAdapterInterface
{
    public function isAvailable(): bool
    {
        return extension_loaded('swoole') && Coroutine::getCid() > 0;
    }

    public function getName(): string
    {
        return 'swoole';
    }

    public function create(callable $fn): mixed
    {
        $result = null;
        $exception = null;

        Coroutine::create(function () use ($fn, &$result, &$exception) {
            try {
                $result = $fn();
            } catch (\Throwable $e) {
                $exception = $e;
            }
        });

        if ($exception) {
            throw $exception;
        }

        return $result;
    }

    public function sleep(float $seconds): void
    {
        Coroutine::sleep($seconds);
    }

    public function parallel(array $callables): array
    {
        $results = [];
        $wg = new Coroutine\WaitGroup();

        foreach ($callables as $index => $callable) {
            $wg->add();
            Coroutine::create(function () use ($callable, &$results, $index, $wg) {
                try {
                    $results[$index] = $callable();
                } finally {
                    $wg->done();
                }
            });
        }

        $wg->wait();
        ksort($results);
        return array_values($results);
    }
}
