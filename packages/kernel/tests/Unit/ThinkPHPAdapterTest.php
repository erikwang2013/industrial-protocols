<?php

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Framework\ThinkPHPAdapter;
use PHPUnit\Framework\TestCase;

class ThinkPHPAdapterTest extends TestCase
{
    public function testThinkPHPAdapterName(): void
    {
        $adapter = new ThinkPHPAdapter();
        $this->assertSame('thinkphp', $adapter->getName());
    }

    public function testThinkPHPAdapterDetectReturnsFalseWithoutThinkPHP(): void
    {
        $adapter = new ThinkPHPAdapter();
        $this->assertFalse($adapter->detect());
    }

    public function testThinkPHPAdapterIsNotLongRunning(): void
    {
        $adapter = new ThinkPHPAdapter();
        $this->assertFalse($adapter->isLongRunning());
    }
}
