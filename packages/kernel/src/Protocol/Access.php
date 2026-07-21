<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Protocol;

enum Access: string
{
    case READ       = 'RO';
    case WRITE      = 'WO';
    case READ_WRITE = 'RW';
}
