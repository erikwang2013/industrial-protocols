<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Framework\WebmanAdapter;
use PHPUnit\Framework\TestCase;

class WebmanAdapterTest extends TestCase
{
    public function testWebmanAdapterName(): void
    {
        $adapter = new WebmanAdapter();
        $this->assertSame('webman', $adapter->getName());
    }

    public function testWebmanAdapterDetectReturnsFalseWithoutWorkerman(): void
    {
        $adapter = new WebmanAdapter();
        $this->assertFalse($adapter->detect());
    }

    public function testWebmanAdapterIsLongRunning(): void
    {
        $adapter = new WebmanAdapter();
        $this->assertTrue($adapter->isLongRunning());
    }
}
