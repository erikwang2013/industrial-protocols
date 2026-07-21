<?php

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface DriverInterface
{
    public function send(FrameInterface $frame): FrameInterface;
    public function sendAsync(FrameInterface $frame): mixed;
    public function getLatency(): float;
    public function supportsAsync(): bool;
}
