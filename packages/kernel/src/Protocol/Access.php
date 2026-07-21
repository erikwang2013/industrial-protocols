<?php

namespace IndustrialProtocols\Protocol;

enum Access: string
{
    case READ       = 'RO';
    case WRITE      = 'WO';
    case READ_WRITE = 'RW';
}
