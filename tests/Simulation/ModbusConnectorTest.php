<?php

namespace IndustrialProtocols\Modbus\Tests\Simulation;

use IndustrialProtocols\Connection\ConnectionState;
use IndustrialProtocols\Exception\ConnectionTimeoutException;
use IndustrialProtocols\Modbus\ModbusConnector;
use IndustrialProtocols\Modbus\ModbusProtocol;
use PHPUnit\Framework\TestCase;

class ModbusConnectorTest extends TestCase
{
    public function testConnectorReadHoldingRegister(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:15020');
        $this->assertNotFalse($server);

        $pid = pcntl_fork();
        if ($pid === 0) {
            $client = @stream_socket_accept($server, 1);
            if ($client) {
                $request = fread($client, 256);
                // Respond with value 42 (0x002A). Match transaction ID from request.
                $tid = substr($request, 0, 2);
                $response = $tid . hex2bin('00000005010302002A');
                fwrite($client, $response);
                fclose($client);
            }
            fclose($server);
            exit(0);
        }

        usleep(50000);

        $connector = new ModbusConnector([
            'host' => '127.0.0.1', 'port' => 15020, 'unit_id' => 1, 'timeout' => 1,
        ]);
        $connector->connect();
        $this->assertTrue($connector->isConnected());

        $result = $connector->read('40001');
        $this->assertSame(42, $result['40001']);

        $connector->disconnect();
        $this->assertFalse($connector->isConnected());

        fclose($server);
        pcntl_waitpid($pid, $status);
    }

    public function testConnectorWriteSingleRegister(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:15021');
        $pid = pcntl_fork();
        if ($pid === 0) {
            $client = @stream_socket_accept($server, 1);
            if ($client) {
                $request = fread($client, 256);
                fwrite($client, $request);
                fclose($client);
            }
            fclose($server);
            exit(0);
        }

        usleep(50000);

        $connector = new ModbusConnector([
            'host' => '127.0.0.1', 'port' => 15021, 'unit_id' => 1, 'timeout' => 1,
        ]);
        $connector->connect();
        $result = $connector->write('40001', [1234]);
        $this->assertSame(1234, $result['40001']);

        $connector->disconnect();
        fclose($server);
        pcntl_waitpid($pid, $status);
    }

    public function testConnectorTimeout(): void
    {
        // Create a server that accepts connections but never responds
        $server = stream_socket_server('tcp://127.0.0.1:15999');
        $this->assertNotFalse($server);

        $pid = pcntl_fork();
        if ($pid === 0) {
            $client = @stream_socket_accept($server, 1);
            if ($client) {
                sleep(10); // never send data, causing read timeout
                fclose($client);
            }
            fclose($server);
            exit(0);
        }

        usleep(50000);

        $connector = new ModbusConnector([
            'host' => '127.0.0.1', 'port' => 15999, 'unit_id' => 1, 'timeout' => 1,
        ]);
        $connector->connect();
        $this->expectException(ConnectionTimeoutException::class);
        $connector->read('40001');
        $connector->disconnect();

        fclose($server);
        pcntl_waitpid($pid, $status);
    }

    public function testModbusProtocolCreateConnector(): void
    {
        $protocol = new ModbusProtocol();
        $this->assertSame('modbus', $protocol->getName());
        $this->assertSame('1.0.0', $protocol->getVersion());
        $this->assertSame(502, $protocol->getDefaultPort());
        $this->assertContains('tcp', $protocol->getSupportedVariants());

        $connector = $protocol->createConnector([
            'host' => '192.168.1.10', 'port' => 502, 'unit_id' => 1, 'timeout' => 3,
        ]);
        $this->assertInstanceOf(ModbusConnector::class, $connector);
    }

    public function testHealthStatus(): void
    {
        // Health check timeout of 3s for connect (test will fail on health if connection is bad)
        $server = stream_socket_server('tcp://127.0.0.1:15998');
        $this->assertNotFalse($server);

        $pid = pcntl_fork();
        if ($pid === 0) {
            $client = @stream_socket_accept($server, 1);
            if ($client) {
                sleep(10);
                fclose($client);
            }
            fclose($server);
            exit(0);
        }

        usleep(50000);

        $connector = new ModbusConnector([
            'host' => '127.0.0.1', 'port' => 15998, 'unit_id' => 1, 'timeout' => 3000,
        ]);

        $health = $connector->getHealth();
        $this->assertSame(ConnectionState::CLOSED, $health->state);

        $connector->connect();
        $health = $connector->getHealth();
        $this->assertSame(ConnectionState::HEALTHY, $health->state);

        $connector->disconnect();

        fclose($server);
        pcntl_waitpid($pid, $status);
    }
}
