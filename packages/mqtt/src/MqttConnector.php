<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Mqtt;

use Erikwang2013\IndustrialProtocols\Connection\HealthStatus;
use Erikwang2013\IndustrialProtocols\Mqtt\Driver\MqttDriver;
use Erikwang2013\IndustrialProtocols\Mqtt\Frame\MqttFrame;
use Erikwang2013\IndustrialProtocols\Protocol\ConnectorInterface;

/**
 * MQTT Connector.
 *
 * Address format:
 *   Read:  'topic/name', 'topic/+/wildcard', 'topic/#' (multi-level wildcard)
 *   Write: ['topic/name' => 'payload', ...]
 */
class MqttConnector implements ConnectorInterface
{
    private MqttDriver $driver;
    private float $subscribeTimeout;
    /** @var array<string, string> last message cache */
    private array $lastMessages = [];

    public function __construct(private array $config)
    {
        $this->driver = new MqttDriver($config);
        $this->subscribeTimeout = $config['subscribe_timeout'] ?? 5.0;
    }

    public function connect(): void
    {
        $this->driver->connect();
    }

    public function disconnect(): void
    {
        $this->driver->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->driver->isConnected();
    }

    public function read(string|array $points): array
    {
        $topics = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($topics as $topic) {
            // Subscribe and wait for the next message on this topic
            $this->driver->subscribe($topic, 0);

            // Wait for a PUBLISH message on this topic
            try {
                $frame = $this->waitForPublish($topic);
                $results[$topic] = $frame->getPayload();
                $this->lastMessages[$topic] = $frame->getPayload();
            } catch (\Throwable) {
                $results[$topic] = $this->lastMessages[$topic] ?? '';
            }
        }

        return $results;
    }

    public function write(string|array $points, array $values): array
    {
        $topics = is_array($points) ? $points : [$points];
        $results = [];

        foreach ($topics as $i => $topic) {
            $value = is_array($values) ? ($values[$topic] ?? $values[$i] ?? '') : (string) $values;
            $this->driver->publish($topic, $value, 0);
            $results[$topic] = $value;
        }

        return $results;
    }

    public function getHealth(): HealthStatus
    {
        if (!$this->isConnected()) {
            return HealthStatus::closed('Not connected');
        }
        return HealthStatus::healthy($this->driver->getLatency());
    }

    /**
     * Get the underlying MQTT driver for advanced usage.
     */
    public function getDriver(): MqttDriver
    {
        return $this->driver;
    }

    /**
     * Publish a raw message to a topic.
     */
    public function publish(string $topic, string $payload, int $qos = 0, bool $retain = false): MqttFrame
    {
        return $this->driver->publish($topic, $payload, $qos, $retain);
    }

    private function waitForPublish(string $topic): MqttFrame
    {
        // In a real implementation, this would loop reading frames
        // until a PUBLISH for the matching topic arrives.
        // For now, return a simple structure.
        return new MqttFrame(
            MqttFrame::TYPE_PUBLISH,
            ['topic' => $topic],
            $this->lastMessages[$topic] ?? '',
        );
    }
}
