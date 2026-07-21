<?php

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface FrameInterface
{
    public function toBytes(): string;
    public static function fromBytes(string $bytes): static;
    public function getData(): array;
}
