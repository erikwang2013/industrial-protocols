<?php
namespace Erikwang2013\IndustrialProtocols\Alert;

interface AlertChannelInterface
{
    public function send(string $title, string $message, string $level = 'info'): void;
}
