<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace Erikwang2013\IndustrialProtocols\Alert;
use Erikwang2013\IndustrialProtocols\Log\LogDriverInterface;

class LogAlertChannel implements AlertChannelInterface
{
    public function __construct(private LogDriverInterface $log) {}

    public function send(string $title, string $message, string $level = 'info'): void
    {
        $levelMap = ['info' => 'INFO', 'warning' => 'WARNING', 'critical' => 'CRITICAL'];
        $logLevel = $levelMap[$level] ?? 'INFO';
        $this->log->log($logLevel, "[ALERT] $title: $message");
    }
}
