<?php

namespace Erikwang2013\IndustrialProtocols\Framework;

class Yii2Adapter implements FrameworkAdapterInterface
{
    public function detect(): bool
    {
        return class_exists('yii\base\Application');
    }

    public function getName(): string
    {
        return 'yii2';
    }

    public function registerConfig(): void
    {
        // Yii2 handles config via its config system and Bootstrap
    }

    public function registerServices(): void
    {
        // Services are registered via BootstrapInterface
    }

    public function registerCommands(): void
    {
        // Commands registered via console configuration
    }

    public function getConfigPath(): string
    {
        return \Yii::getAlias('@app/config/industrial-protocols.php');
    }

    public function isLongRunning(): bool
    {
        return defined('SWOOLE_YII2') || class_exists('Swoole\Coroutine');
    }
}
