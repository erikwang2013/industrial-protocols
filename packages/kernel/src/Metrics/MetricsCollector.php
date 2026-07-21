<?php

namespace Erikwang2013\IndustrialProtocols\Metrics;

class MetricsCollector
{
    /** @var array<string, int|float> */
    private array $counters = [];

    /** @var array<string, float> */
    private array $gauges = [];

    /** @var array<string, array<float>> */
    private array $histograms = [];

    public function incrementCounter(string $name, array $labels = [], int $by = 1): void
    {
        $key = $this->metricKey($name, $labels);
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $by;
    }

    public function setGauge(string $name, float $value, array $labels = []): void
    {
        $key = $this->metricKey($name, $labels);
        $this->gauges[$key] = $value;
    }

    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        $key = $this->metricKey($name, $labels);
        $this->histograms[$key][] = $value;
    }

    public function getCounter(string $name, array $labels = []): int|float
    {
        return $this->counters[$this->metricKey($name, $labels)] ?? 0;
    }

    public function getGauge(string $name, array $labels = []): float
    {
        return $this->gauges[$this->metricKey($name, $labels)] ?? 0.0;
    }

    /** @return array<float> */
    public function getHistogram(string $name, array $labels = []): array
    {
        return $this->histograms[$this->metricKey($name, $labels)] ?? [];
    }

    /**
     * Export all metrics in Prometheus text format.
     */
    public function toPrometheus(string $namespace = 'industrial'): string
    {
        $lines = [];

        foreach ($this->counters as $key => $value) {
            [$name, $labelStr] = $this->parseKey($key);
            $lines[] = $this->formatMetric("{$namespace}_{$name}", 'counter', $value, $labelStr);
        }

        foreach ($this->gauges as $key => $value) {
            [$name, $labelStr] = $this->parseKey($key);
            $lines[] = $this->formatMetric("{$namespace}_{$name}", 'gauge', $value, $labelStr);
        }

        foreach ($this->histograms as $key => $buckets) {
            [$name, $labelStr] = $this->parseKey($key);
            if (empty($buckets)) continue;
            sort($buckets);
            $count = count($buckets);
            $sum = array_sum($buckets);
            $lines[] = "# HELP {$namespace}_{$name} Histogram of {$name}";
            $lines[] = "# TYPE {$namespace}_{$name} histogram";
            $lines[] = "{$namespace}_{$name}_count{$labelStr} $count";
            $lines[] = "{$namespace}_{$name}_sum{$labelStr} $sum";
        }

        return implode("\n", $lines) . "\n";
    }

    private function metricKey(string $name, array $labels): string
    {
        if (empty($labels)) return $name;
        ksort($labels);
        $parts = [$name];
        foreach ($labels as $k => $v) {
            $parts[] = "$k=$v";
        }
        return implode('|', $parts);
    }

    /** @return array{string, string} */
    private function parseKey(string $key): array
    {
        $parts = explode('|', $key);
        $name = array_shift($parts);
        if (empty($parts)) return [$name, ''];

        $labelPairs = [];
        foreach ($parts as $p) {
            [$k, $v] = explode('=', $p, 2);
            $labelPairs[] = "$k=\"$v\"";
        }
        return [$name, '{' . implode(',', $labelPairs) . '}'];
    }

    private function formatMetric(string $name, string $type, int|float $value, string $labels): string
    {
        return "$name$labels $value";
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->histograms = [];
    }
}
