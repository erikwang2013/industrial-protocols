<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Modbus;

use Erikwang2013\IndustrialProtocols\Connection\ConnectionState;
use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Exception\AddressOutOfRangeException;
use Erikwang2013\IndustrialProtocols\Modbus\Driver\ModbusRtuDriver;
use Erikwang2013\IndustrialProtocols\Modbus\Driver\ModbusTcpDriver;
use Erikwang2013\IndustrialProtocols\Modbus\Frame\ModbusRequest;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;

class ModbusConnector implements ConnectorInterface
{
    private DriverInterface $driver;

    public function __construct(private array $config)
    {
        $variant = $config['variant'] ?? 'tcp';

        if ($variant === 'rtu') {
            $this->driver = new ModbusRtuDriver(
                $config['device'] ?? '/dev/ttyUSB0',
                $config['baud_rate'] ?? 19200,
                $config['parity'] ?? 'N',
                $config['data_bits'] ?? 8,
                $config['stop_bits'] ?? 1,
                ($config['timeout'] ?? 3000) / 1000.0,
            );
        } else {
            $this->driver = new ModbusTcpDriver(
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 502,
                ($config['timeout'] ?? 3000) / 1000.0,
            );
        }
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
