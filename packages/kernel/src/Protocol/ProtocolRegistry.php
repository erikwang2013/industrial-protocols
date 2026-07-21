<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Protocol;

class ProtocolRegistry
{
    /** @var array<string, ProtocolInterface> */
    private array $protocols = [];

    public function register(ProtocolInterface $protocol): void
    {
        $this->protocols[$protocol->getName()] = $protocol;
    }

    public function get(string $name): ProtocolInterface
    {
        if (!isset($this->protocols[$name])) {
            throw new \RuntimeException("Protocol not registered: $name");
        }
        return $this->protocols[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->protocols[$name]);
    }

    /** @return array<string, ProtocolInterface> */
    public function all(): array
    {
        return $this->protocols;
    }

    public function autoDiscover(string $installedJsonPath): int
    {
        $count = 0;
        if (!file_exists($installedJsonPath)) return $count;

        $installed = json_decode(file_get_contents($installedJsonPath), true);
        foreach ($installed['packages'] ?? [] as $pkg) {
            $protocolClass = $pkg['extra']['industrial-protocols']['protocol'] ?? null;
            if ($protocolClass && class_exists($protocolClass)) {
                $instance = new $protocolClass();
                if ($instance instanceof ProtocolInterface) {
                    $this->register($instance);
                    $count++;
                }
            }
        }
        return $count;
    }
}
