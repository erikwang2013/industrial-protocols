<?php

namespace IndustrialProtocols\Framework\Laravel;

use Illuminate\Support\Facades\Facade;

class IndustrialProtocolsFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'industrial-protocols';
    }
}
