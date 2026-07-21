<?php
namespace Erikwang2013\IndustrialProtocols\Log;
class NullLogDriver implements LogDriverInterface
{
    public function log(string $level, string $message, array $context = []): void {}
    public function event(object $event): void {}
}
