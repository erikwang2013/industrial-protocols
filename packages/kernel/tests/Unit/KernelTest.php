<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Kernel;
use IndustrialProtocols\Protocol\ConnectorInterface;
use IndustrialProtocols\Protocol\ProtocolInterface;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        $this->configPath = sys_get_temp_dir() . '/kernel-test-' . uniqid() . '.php';
        file_put_contents($this->configPath, '<?php return ' . var_export([
            'devices' => [
                'plc-001' => [
                    'protocol' => 'mock',
                    'host'     => '127.0.0.1',
                    'port'     => 9999,
                    'timeout'  => 1000,
                ],
            ],
            'gateway' => ['rules' => []],
            'health_check_interval' => 30,
        ], true) . ';');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function testKernelBoot(): void
    {
        $kernel = new Kernel(['config_path' => $this->configPath]);
        $kernel->boot();

        $this->assertNotNull($kernel->getConnectionManager());
        $this->assertNotNull($kernel->getProtocolRegistry());
        $this->assertNotNull($kernel->getConfigRepository());
        $this->assertSame('plain', $kernel->getFramework()->getName());
    }

    public function testKernelRegisterProtocol(): void
    {
        $kernel = new Kernel(['config_path' => $this->configPath]);
        $kernel->boot();

        $protocol = $this->createMock(ProtocolInterface::class);
        $protocol->method('getName')->willReturn('mock');
        $protocol->method('createConnector')->willReturn($this->createMock(ConnectorInterface::class));

        $kernel->getProtocolRegistry()->register($protocol);
        $this->assertTrue($kernel->getProtocolRegistry()->has('mock'));
    }

    public function testKernelShutdown(): void
    {
        $kernel = new Kernel(['config_path' => $this->configPath]);
        $kernel->boot();
        $kernel->shutdown();
        $this->assertTrue(true);
    }

    public function testKernelBootWithInvalidConfigPathThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $kernel = new Kernel(['config_path' => '/nonexistent/config.php']);
        $kernel->boot();
    }

    public function testGetConnectionManagerBeforeBootThrows(): void
    {
        $kernel = new Kernel(['config_path' => $this->configPath]);
        $this->expectException(\RuntimeException::class);
        $kernel->getConnectionManager();
    }
}
