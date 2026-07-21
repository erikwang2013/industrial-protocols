<?php
namespace IndustrialProtocols\Tests\Unit;
use IndustrialProtocols\Alert\AlertManager;
use IndustrialProtocols\Alert\LogAlertChannel;
use IndustrialProtocols\Log\NullLogDriver;
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
