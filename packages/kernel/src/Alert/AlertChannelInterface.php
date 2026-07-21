<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace Erikwang2013\IndustrialProtocols\Alert;

interface AlertChannelInterface
{
    public function send(string $title, string $message, string $level = 'info'): void;
}
