<?php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Config\DatabaseConfigRepository;
use PHPUnit\Framework\TestCase;

class DatabaseConfigRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private DatabaseConfigRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->repo = new DatabaseConfigRepository($this->pdo);
    }

    public function testSetAndGetDeviceConfig(): void
    {
        $this->repo->setDeviceConfig('plc-001', [
            'protocol' => 'modbus',
            'host'     => '192.168.1.10',
            'port'     => 502,
            'unit_id'  => 1,
        ]);

        $config = $this->repo->getDeviceConfig('plc-001');
        $this->assertSame('modbus', $config['protocol']);
        $this->assertSame(502, $config['port']);
    }

    public function testGetAllDeviceConfigs(): void
    {
        $this->repo->setDeviceConfig('plc-001', ['protocol' => 'modbus', 'host' => '10.0.0.1', 'port' => 502]);
        $this->repo->setDeviceConfig('plc-002', ['protocol' => 'opcua', 'host' => '10.0.0.2', 'port' => 4840]);

        $all = $this->repo->getAllDeviceConfigs();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('plc-001', $all);
        $this->assertArrayHasKey('plc-002', $all);
    }

    public function testRemoveDeviceConfig(): void
    {
        $this->repo->setDeviceConfig('plc-001', ['protocol' => 'modbus', 'host' => '10.0.0.1', 'port' => 502]);
        $this->repo->removeDeviceConfig('plc-001');

        $this->expectException(\RuntimeException::class);
        $this->repo->getDeviceConfig('plc-001');
    }

    public function testDataPoints(): void
    {
        $this->repo->setDeviceConfig('plc-001', ['protocol' => 'modbus', 'host' => '10.0.0.1', 'port' => 502]);
        $this->repo->setDataPoints('plc-001', [
            ['address' => '40001', 'name' => 'temperature', 'type' => 'FLOAT32'],
        ]);

        $points = $this->repo->getDataPoints('plc-001');
        $this->assertCount(1, $points);
        $this->assertSame('temperature', $points[0]['name']);
    }

    public function testGatewayRules(): void
    {
        $this->repo->addGatewayRule(['id' => 'gw-001', 'source' => 'plc-001', 'target' => 'plc-002']);
        $rules = $this->repo->getGatewayRules();
        $this->assertCount(1, $rules);
        $this->assertSame('gw-001', $rules[0]['id']);

        $this->repo->removeGatewayRule('gw-001');
        $this->assertCount(0, $this->repo->getGatewayRules());
    }
}
