<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Framework\Laravel;

use Illuminate\Support\Facades\Facade;

class IndustrialProtocolsFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'industrial-protocols';
    }
}
