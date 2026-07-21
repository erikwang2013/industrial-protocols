<?php
namespace Erikwang2013\IndustrialProtocols\Log;
interface LogDriverInterface
{
    public function log(string $level, string $message, array $context = []): void;
    public function event(object $event): void;
}
