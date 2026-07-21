<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Framework\LaravelAdapter;
use PHPUnit\Framework\TestCase;

class LaravelAdapterTest extends TestCase
{
    public function testLaravelAdapterName(): void
    {
        $adapter = new LaravelAdapter();
        $this->assertSame('laravel', $adapter->getName());
    }

    public function testLaravelAdapterDetectReturnsFalseWithoutLaravel(): void
    {
        $adapter = new LaravelAdapter();
        // In test environment without Laravel, should return false
        $this->assertFalse($adapter->detect());
    }

    public function testLaravelAdapterIsNotLongRunningByDefault(): void
    {
        $adapter = new LaravelAdapter();
        $this->assertFalse($adapter->isLongRunning());
    }
}
