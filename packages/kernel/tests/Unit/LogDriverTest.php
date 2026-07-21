<?php
namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Event\DataReadEvent;
use Erikwang2013\IndustrialProtocols\Log\NullLogDriver;
use Erikwang2013\IndustrialProtocols\Log\PsrLogDriver;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class LogDriverTest extends TestCase
{
    public function testPsrLogDriverLogsToPsrLogger(): void
    {
        $logger = new class extends AbstractLogger {
            public array $logs = [];
            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = compact('level', 'message', 'context');
            }
        };
        $driver = new PsrLogDriver($logger);
        $driver->log('ERROR', 'test message', ['key' => 'value']);
        $this->assertCount(1, $logger->logs);
        $this->assertSame('ERROR', $logger->logs[0]['level']);
    }

    public function testPsrLogDriverLogsEvent(): void
    {
        $logger = new class extends AbstractLogger {
            public array $logs = [];
            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = compact('level', 'message');
            }
        };
        $driver = new PsrLogDriver($logger);
        $driver->event(new DataReadEvent('plc-001', ['40001' => 42], 10.0));
        $this->assertCount(1, $logger->logs);
    }

    public function testNullLogDriverDoesNothing(): void
    {
        $driver = new NullLogDriver();
        $driver->log('ERROR', 'test');
        $driver->event(new DataReadEvent('plc-001', [], 0));
        $this->assertTrue(true);
    }
}
