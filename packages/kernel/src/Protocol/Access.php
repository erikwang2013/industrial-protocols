<?php

namespace Erikwang2013\IndustrialProtocols\Protocol;

enum Access: string
{
    case READ       = 'RO';
    case WRITE      = 'WO';
    case READ_WRITE = 'RW';
}
