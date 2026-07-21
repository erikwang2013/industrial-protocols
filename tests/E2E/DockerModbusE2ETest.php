<?php

namespace Erikwang2013\IndustrialProtocols\Tests\E2E;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;
use PHPUnit\Framework\TestCase;

/**
 * E2E test that requires the Docker Modbus simulator running on localhost:5020.
 * Skip if service is not reachable.
 */
class DockerModbusE2ETest extends TestCase
{
    private const SIMULATOR_HOST = '127.0.0.1';
    private const SIMULATOR_PORT = 5020;

    private $kernel = null;
    private string $configPath;

    protected function setUp(): void
    {
        // Check if simulator is running
        $fp = @fsockopen(self::SIMULATOR_HOST, self::SIMULATOR_PORT, $errno, $errstr, 1);
        if (!$fp) {
            $this->markTestSkipped("Docker Modbus simulator not running at " . self::SIMULATOR_HOST . ":" . self::SIMULATOR_PORT);
        }
        fclose($fp);

        $this->configPath = sys_get_temp_dir() . '/docker-e2e-' . uniqid() . '.php';
        file_put_contents($this->configPath, '<?php return ' . var_export([
            'devices' => [
                'docker-plc' => [
                    'protocol' => 'modbus',
                    'variant'  => 'tcp',
                    'host'     => self::SIMULATOR_HOST,
                    'port'     => self::SIMULATOR_PORT,
                    'unit_id'  => 1,
                    'timeout'  => 3000,
                ],
            ],
            'gateway' => ['rules' => []],
            'health_check_interval' => 30,
        ], true) . ';');
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }
    }

    public function testReadFromDockerSimulator(): void
    {
        $this->kernel = new Kernel(['config_path' => $this->configPath]);
        $this->kernel->getProtocolRegistry()->register(new ModbusProtocol());
        $this->kernel->boot();

        $conn = $this->kernel->getConnectionManager()->connect('docker-plc');
        $this->assertTrue($conn->isConnected(), 'Should connect to Docker simulator');

        $result = $conn->read('40001');
        $this->assertSame(42, $result['40001'], 'Register 40001 should return 42');

        $result2 = $conn->read('40002');
        $this->assertSame(100, $result2['40002'], 'Register 40002 should return 100');

        $health = $this->kernel->getConnectionManager()->health('docker-plc');
        $this->assertSame(ConnectionState::HEALTHY, $health->state);
    }

    public function testWriteToDockerSimulator(): void
    {
        $this->kernel = new Kernel(['config_path' => $this->configPath]);
        $this->kernel->getProtocolRegistry()->register(new ModbusProtocol());
        $this->kernel->boot();

        $conn = $this->kernel->getConnectionManager()->connect('docker-plc');

        // Write value using the standard write(points, values) signature
        $result = $conn->write(['40003'], [999]);
        $this->assertSame(999, $result['40003']);

        // Read back
        $readback = $conn->read('40003');
        $this->assertSame(999, $readback['40003']);
    }

    public function testHealthCheckAllConnections(): void
    {
        $this->kernel = new Kernel(['config_path' => $this->configPath]);
        $this->kernel->getProtocolRegistry()->register(new ModbusProtocol());
        $this->kernel->boot();

        $this->kernel->getConnectionManager()->connect('docker-plc');
        $allHealth = $this->kernel->getConnectionManager()->healthAll();

        $this->assertArrayHasKey('docker-plc', $allHealth);
        $this->assertSame(ConnectionState::HEALTHY, $allHealth['docker-plc']->state);
    }
}
