<?php

namespace IndustrialProtocols\Modbus;

use IndustrialProtocols\Connection\ConnectionState;
use IndustrialProtocols\Connection\HealthStatus;
use IndustrialProtocols\Exception\AddressOutOfRangeException;
use IndustrialProtocols\Modbus\Driver\ModbusTcpDriver;
use IndustrialProtocols\Modbus\Frame\ModbusRequest;
use IndustrialProtocols\Protocol\ConnectorInterface;

class ModbusConnector implements ConnectorInterface
{
    private ModbusTcpDriver $driver;

    public function __construct(private array $config)
    {
        $this->driver = new ModbusTcpDriver(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 502,
            ($config['timeout'] ?? 3000) / 1000.0,
        );
    }

    public function connect(): void { $this->driver->connect(); }
    public function disconnect(): void { $this->driver->disconnect(); }
    public function isConnected(): bool { return $this->driver->isConnected(); }

    public function read(string|array $points): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $address) {
            $regAddr = $this->parseAddress($address);
            $request = ModbusRequest::readHoldingRegisters(
                $this->config['unit_id'] ?? 1, $regAddr, 1,
            );
            $response = $this->driver->send($request);
            $registers = $response->getRegisters();
            $results[$address] = $registers[0] ?? null;
        }
        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $results = [];
        foreach ($addresses as $i => $address) {
            $value = is_array($values) ? ($values[$address] ?? $values[$i] ?? null) : $values;
            $regAddr = $this->parseAddress($address);
            $request = ModbusRequest::writeSingleRegister(
                $this->config['unit_id'] ?? 1, $regAddr, (int)$value,
            );
            $this->driver->send($request);
            $results[$address] = $value;
        }
        return $results;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) return HealthStatus::closed('Not connected');
        return HealthStatus::healthy($this->driver->getLatency());
    }

    private function parseAddress(string $address): int
    {
        $addr = (int)$address;
        if ($addr >= 40001 && $addr <= 49999) return $addr - 40001;
        if ($addr >= 30001 && $addr <= 39999) return $addr - 30001;
        if ($addr >= 0 && $addr <= 9999) return $addr;
        throw new AddressOutOfRangeException("Invalid Modbus address: $address");
    }
}
