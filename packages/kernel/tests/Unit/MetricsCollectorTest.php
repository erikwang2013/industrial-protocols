<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Metrics\MetricsCollector;
use PHPUnit\Framework\TestCase;

class MetricsCollectorTest extends TestCase
{
    public function testCounterIncrement(): void
    {
        $m = new MetricsCollector();
        $m->incrementCounter('connections_total', ['protocol' => 'modbus']);
        $m->incrementCounter('connections_total', ['protocol' => 'modbus'], 2);
        $this->assertSame(3, $m->getCounter('connections_total', ['protocol' => 'modbus']));
    }

    public function testGaugeSet(): void
    {
        $m = new MetricsCollector();
        $m->setGauge('active_connections', 5.0);
        $this->assertSame(5.0, $m->getGauge('active_connections'));
        $m->setGauge('active_connections', 3.0);
        $this->assertSame(3.0, $m->getGauge('active_connections'));
    }

    public function testHistogramObserve(): void
    {
        $m = new MetricsCollector();
        $m->observeHistogram('read_latency_ms', 15.5);
        $m->observeHistogram('read_latency_ms', 23.1);
        $m->observeHistogram('read_latency_ms', 8.2);
        $buckets = $m->getHistogram('read_latency_ms');
        $this->assertCount(3, $buckets);
        $this->assertSame(46.8, array_sum($buckets));
    }

    public function testPrometheusExport(): void
    {
        $m = new MetricsCollector();
        $m->incrementCounter('reads_total', ['device' => 'plc-001'], 10);
        $m->setGauge('connection_state', 1.0, ['device' => 'plc-001']);
        $m->observeHistogram('latency_ms', 5.0, ['device' => 'plc-001']);

        $output = $m->toPrometheus('industrial');
        $this->assertStringContainsString('industrial_reads_total', $output);
        $this->assertStringContainsString('device="plc-001"', $output);
        $this->assertStringContainsString('industrial_connection_state', $output);
        $this->assertStringContainsString('industrial_latency_ms_count', $output);
    }

    public function testReset(): void
    {
        $m = new MetricsCollector();
        $m->incrementCounter('test');
        $m->reset();
        $this->assertSame(0, $m->getCounter('test'));
    }
}
