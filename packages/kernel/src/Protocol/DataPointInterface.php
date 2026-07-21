<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Protocol;

interface DataPointInterface
{
    public function getAddress(): string;
    public function getType(): DataType;
    public function getAccess(): Access;
}
