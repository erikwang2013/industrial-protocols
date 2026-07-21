<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Tests\Integration;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Kernel;
use Erikwang2013\IndustrialProtocols\Modbus\ModbusProtocol;
use PHPUnit\Framework\TestCase;

class KernelModbusIntegrationTest extends TestCase
{
    public function testFullFlowKernelWithModbus(): void
    {
        $configPath = sys_get_temp_dir() . '/integration-' . uniqid() . '.php';
        file_put_contents($configPath, '<?php return ' . var_export([
            'devices' => [
                'test-plc' => [
                    'protocol' => 'modbus',
                    'variant'  => 'tcp',
                    'host'     => '127.0.0.1',
                    'port'     => 15030,
                    'unit_id'  => 1,
                    'timeout'  => 2000,
                ],
            ],
            'gateway' => ['rules' => []],
            'health_check_interval' => 30,
        ], true) . ';');

        $server = stream_socket_server('tcp://127.0.0.1:15030');
        $pid = pcntl_fork();
        if ($pid === 0) {
            $client = @stream_socket_accept($server, 2);
            if ($client) {
                fread($client, 256);
                $tid = "\x00\x01";
                $response = $tid . hex2bin('00000005010302002A');
                fwrite($client, $response);
                fclose($client);
            }
            fclose($server);
            exit(0);
        }
        usleep(50000);

        try {
            $kernel = new Kernel(['config_path' => $configPath]);
            $kernel->getProtocolRegistry()->register(new ModbusProtocol());
            $kernel->boot();

            $conn = $kernel->getConnectionManager()->connect('test-plc');
            $this->assertTrue($conn->isConnected());

            $result = $conn->read('40001');
            $this->assertSame(42, $result['40001']);

            $health = $kernel->getConnectionManager()->health('test-plc');
            $this->assertSame(ConnectionState::HEALTHY, $health->state);

            $kernel->shutdown();
        } finally {
            fclose($server);
            pcntl_waitpid($pid, $status);
            if (file_exists($configPath)) unlink($configPath);
        }
    }

    public function testPlainPhpAdapterUsage(): void
    {
        $configPath = sys_get_temp_dir() . '/quickstart-' . uniqid() . '.php';
        file_put_contents($configPath, '<?php return ' . var_export([
            'devices' => [
                'plc-001' => [
                    'protocol' => 'modbus',
                    'host'     => '192.168.1.10',
                    'port'     => 502,
                    'unit_id'  => 1,
                    'timeout'  => 3000,
                ],
            ],
            'gateway' => ['rules' => []],
            'health_check_interval' => 30,
        ], true) . ';');

        $kernel = new Kernel(['config_path' => $configPath]);
        $kernel->getProtocolRegistry()->register(new ModbusProtocol());
        $kernel->boot();

        $this->assertSame('plain', $kernel->getFramework()->getName());

        $kernel->shutdown();

        if (file_exists($configPath)) unlink($configPath);
    }
}
