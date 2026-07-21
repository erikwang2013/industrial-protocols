<?php
namespace Erikwang2013\IndustrialProtocols\Log;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
class PsrLogDriver implements LogDriverInterface
{
    public function __construct(private LoggerInterface $logger = new NullLogger()) {}
    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
    public function event(object $event): void
    {
        $this->logger->info($event::class, (array)$event);
    }
}
