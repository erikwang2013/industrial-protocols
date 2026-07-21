<?php

namespace IndustrialProtocols\Tests\Unit;

use IndustrialProtocols\Framework\Yii2Adapter;
use PHPUnit\Framework\TestCase;

class Yii2AdapterTest extends TestCase
{
    public function testYii2AdapterName(): void
    {
        $adapter = new Yii2Adapter();
        $this->assertSame('yii2', $adapter->getName());
    }

    public function testYii2AdapterDetectReturnsFalseWithoutYii2(): void
    {
        $adapter = new Yii2Adapter();
        $this->assertFalse($adapter->detect());
    }

    public function testYii2AdapterIsLongRunningWhenSwooleAvailable(): void
    {
        $adapter = new Yii2Adapter();
        // Swoole is installed in this environment, so long-running is true
        $expected = class_exists('Swoole\Coroutine') || defined('SWOOLE_YII2');
        $this->assertSame($expected, $adapter->isLongRunning());
    }
}
