<?php

namespace Erikwang2013\IndustrialProtocols\Coroutine;

interface CoroutineAdapterInterface
{
    public function isAvailable(): bool;
    public function getName(): string;
    public function create(callable $fn): mixed;
    public function sleep(float $seconds): void;
    public function parallel(array $callables): array;
}
