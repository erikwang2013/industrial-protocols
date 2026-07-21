<?php

namespace IndustrialProtocols\Protocol;

interface DriverInterface
{
    public function send(FrameInterface $frame): FrameInterface;
    public function sendAsync(FrameInterface $frame): mixed;
    public function getLatency(): float;
    public function supportsAsync(): bool;
}
