<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Config\FileConfigRepository;
use PHPUnit\Framework\TestCase;

class FileConfigRepositoryTest extends TestCase
{
    private string $tmpFile;
    private FileConfigRepository $repo;

    protected function setUp(): void
    {
        $this->tmpFile = sys_get_temp_dir() . '/industrial-test-' . uniqid() . '.php';
        file_put_contents($this->tmpFile, '<?php return ' . var_export([
            'devices' => [
                'plc-001' => [
                    'protocol' => 'modbus',
                    'variant'  => 'tcp',
                    'host'     => '192.168.1.10',
                    'port'     => 502,
                    'unit_id'  => 1,
                    'timeout'  => 3000,
                ],
            ],
            'gateway' => ['rules' => []],
            'health_check_interval' => 30,
        ], true) . ';');

        $this->repo = new FileConfigRepository($this->tmpFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tmpFile)) {
            unlink($this->tmpFile);
        }
    }

    public function testGetDeviceConfig(): void
    {
        $config = $this->repo->getDeviceConfig('plc-001');
        $this->assertSame('modbus', $config['protocol']);
        $this->assertSame('192.168.1.10', $config['host']);
        $this->assertSame(502, $config['port']);
    }

    public function testGetAllDeviceConfigs(): void
    {
        $configs = $this->repo->getAllDeviceConfigs();
        $this->assertCount(1, $configs);
        $this->assertArrayHasKey('plc-001', $configs);
    }

    public function testGetDeviceConfigThrowsIfNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->repo->getDeviceConfig('non-existent');
    }

    public function testSetAndRemoveDeviceConfig(): void
    {
        $this->repo->setDeviceConfig('plc-002', [
            'protocol' => 'opcua',
            'host'     => '10.0.0.1',
            'port'     => 4840,
        ]);
        $this->assertSame('opcua', $this->repo->getDeviceConfig('plc-002')['protocol']);

        $this->repo->removeDeviceConfig('plc-002');
        $this->expectException(\RuntimeException::class);
        $this->repo->getDeviceConfig('plc-002');
    }

    public function testDataPoints(): void
    {
        $this->repo->setDataPoints('plc-001', [
            ['address' => '40001', 'name' => 'temp', 'type' => 'FLOAT32', 'access' => 'RW'],
        ]);
        $points = $this->repo->getDataPoints('plc-001');
        $this->assertCount(1, $points);
        $this->assertSame('temp', $points[0]['name']);
    }

    public function testGatewayRules(): void
    {
        $this->repo->addGatewayRule(['id' => 'gw-001', 'source_device' => 'plc-001', 'source_point' => '40001']);
        $rules = $this->repo->getGatewayRules();
        $this->assertCount(1, $rules);

        $this->repo->removeGatewayRule('gw-001');
        $this->assertCount(0, $this->repo->getGatewayRules());
    }
}
