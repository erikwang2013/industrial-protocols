<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
namespace Erikwang2013\IndustrialProtocols\Tests\Unit;
use Erikwang2013\IndustrialProtocols\Alert\AlertManager;
use Erikwang2013\IndustrialProtocols\Alert\LogAlertChannel;
use Erikwang2013\IndustrialProtocols\Log\NullLogDriver;
use PHPUnit\Framework\TestCase;

class AlertTest extends TestCase
{
    public function testAlertManagerSendToMultipleChannels(): void
    {
        $manager = new AlertManager();
        $manager->addChannel('log1', new LogAlertChannel(new NullLogDriver()));
        $manager->addChannel('log2', new LogAlertChannel(new NullLogDriver()));
        $this->assertCount(2, $manager->getChannels());
        $manager->send('Test', 'Message', 'warning');
        $this->assertTrue(true); // no exceptions
    }

    public function testAlertManagerHandlesChannelFailure(): void
    {
        $manager = new AlertManager();
        // No channels added - send should not throw
        $manager->send('Test', 'Message');
        $this->assertTrue(true);
    }
}
