<?php

namespace Swoole\Coroutine;

class WaitGroup
{
    public function add(int $delta = 1): bool {}

    public function done(): bool {}

    public function wait(float $timeout = -1): bool {}
}
