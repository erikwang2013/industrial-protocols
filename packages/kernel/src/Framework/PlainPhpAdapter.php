<?php

namespace Erikwang2013\IndustrialProtocols\Framework;

class PlainPhpAdapter implements FrameworkAdapterInterface
{
    public function __construct(private string $configPath) {}

    public function detect(): bool { return true; }
    public function getName(): string { return 'plain'; }
    public function registerConfig(): void {}
    public function registerServices(): void {}
    public function registerCommands(): void {}
    public function getConfigPath(): string { return $this->configPath; }
    public function isLongRunning(): bool { return false; }
}
