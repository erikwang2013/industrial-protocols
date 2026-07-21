<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\OpcUa\Driver\OpcUaDriver;
use Erikwang2013\IndustrialProtocols\OpcUa\Services\SessionManager;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\NodeId;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\Variant;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

class OpcUaConnector implements ConnectorInterface
{
    private OpcUaDriver $driver;
    private ?SessionManager $session = null;

    public function __construct(private array $config) {}

    public function connect(): void
    {
        $host = $this->config['host'] ?? '127.0.0.1';
        $port = $this->config['port'] ?? 4840;
        $timeout = ($this->config['timeout'] ?? 5000) / 1000.0;
        $endpointUrl = $this->config['endpoint_url'] ?? "opc.tcp://{$host}:{$port}";

        $this->driver = new OpcUaDriver($host, $port, $timeout, $endpointUrl);
        $this->driver->connect();

        // Create and activate session
        $appUri = $this->config['application_uri'] ?? 'urn:php:industrial-protocols';
        $sessionName = $this->config['session_name'] ?? 'PHP-OPCUA-Client';

        $this->session = new SessionManager($this->driver->getSecureChannel());
        $this->session->createSession($appUri, $sessionName);
        $this->session->activateSession();
    }

    public function disconnect(): void
    {
        $this->session = null;
        if (isset($this->driver)) {
            $this->driver->disconnect();
        }
    }

    public function isConnected(): bool
    {
        return isset($this->driver) && $this->driver->isConnected() && $this->session !== null;
    }

    /**
     * Read OPC UA node values.
     *
     * Point format: "ns=0;i=2258" or "ns=2;s=MyVariable" or "i=2258"
     *
     * @param string|array<string> $points
     * @return array<string, mixed>
     */
    public function read(string|array $points): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $nodes = [];
        foreach ($addresses as $addr) {
            $nodes[] = ['nodeId' => $this->parseNodeId($addr), 'attributeId' => 13];
        }

        $results = [];
        $responses = $this->session->read($nodes);
        foreach ($addresses as $i => $addr) {
            $results[$addr] = $responses[$i]['value'] ?? null;
        }
        return $results;
    }

    /**
     * Write OPC UA node values.
     *
     * @param string|array<string> $points
     * @param array<string, mixed> $values
     * @return array<string, int>
     */
    public function write(string|array $points, array $values): array
    {
        $addresses = is_array($points) ? $points : [$points];
        $nodes = [];
        foreach ($addresses as $i => $addr) {
            $value = is_array($values) ? ($values[$addr] ?? $values[$i] ?? null) : $values;
            $nodes[] = [
                'nodeId' => $this->parseNodeId($addr),
                'attributeId' => 13,
                'value' => match (true) {
                    is_float($value) => Variant::double($value),
                    is_int($value)   => Variant::int32($value),
                    is_bool($value)  => Variant::bool($value),
                    default          => Variant::string((string) $value),
                },
            ];
        }

        $results = [];
        $responses = $this->session->write($nodes);
        foreach ($addresses as $i => $addr) {
            $results[$addr] = $responses[$i]['statusCode'] ?? 0;
        }
        return $results;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) {
            return HealthStatus::closed('Not connected');
        }
        return HealthStatus::healthy(0.0);
    }

    /**
     * Browse the address space starting from a node.
     *
     * @return string[] Array of browse-name strings found under the given node
     */
    public function browse(string $nodeId = 'i=84'): array
    {
        return $this->session->browse($this->parseNodeId($nodeId));
    }

    /**
     * Parse an OPC UA node ID string.
     *
     * Supported formats:
     *   - "ns=0;i=2258"      (namespace + numeric)
     *   - "ns=2;s=Temperature" (namespace + string)
     *   - "i=2258"            (numeric, namespace 0)
     *   - "s=MyVar"           (string, namespace 0)
     *   - raw numeric         (treated as numeric identifier, namespace 0)
     */
    private function parseNodeId(string $nodeId): NodeId
    {
        if (preg_match('/^ns=(\d+);i=(\d+)$/', $nodeId, $m)) {
            return new NodeId((int) $m[1], (int) $m[2]);
        }
        if (preg_match('/^ns=(\d+);s=(.+)$/', $nodeId, $m)) {
            return new NodeId((int) $m[1], $m[2]);
        }
        if (preg_match('/^i=(\d+)$/', $nodeId, $m)) {
            return new NodeId(0, (int) $m[1]);
        }
        if (preg_match('/^s=(.+)$/', $nodeId, $m)) {
            return new NodeId(0, $m[1]);
        }
        // Fallback: try as raw numeric
        if (is_numeric($nodeId)) {
            return new NodeId(0, (int) $nodeId);
        }
        return new NodeId(0, $nodeId);
    }
}
