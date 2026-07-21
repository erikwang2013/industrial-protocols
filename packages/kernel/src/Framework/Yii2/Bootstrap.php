<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Framework\Yii2;

use Erikwang2013\IndustrialProtocols\Kernel;
use yii\base\BootstrapInterface;

class Bootstrap implements BootstrapInterface
{
    public function bootstrap($app): void
    {
        $configPath = \Yii::getAlias('@app/config/industrial-protocols.php');

        $kernel = new Kernel(['config_path' => $configPath]);
        $kernel->getProtocolRegistry()->autoDiscover(
            \Yii::getAlias('@app/vendor/composer/installed.json')
        );
        $kernel->boot();

        $app->set('industrial-protocols', $kernel);
    }
}
