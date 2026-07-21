<?php
namespace IndustrialProtocols\Alert;

interface AlertChannelInterface
{
    public function send(string $title, string $message, string $level = 'info'): void;
}
