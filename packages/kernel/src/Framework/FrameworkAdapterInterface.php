<?php

namespace IndustrialProtocols\Framework;

interface FrameworkAdapterInterface
{
    public function detect(): bool;
    public function getName(): string;
    public function registerConfig(): void;
    public function registerServices(): void;
    public function registerCommands(): void;
    public function getConfigPath(): string;
    public function isLongRunning(): bool;
}
