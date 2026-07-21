<?php

namespace Erikwang2013\IndustrialProtocols\Connection;

enum ConnectionState: string
{
    case HEALTHY   = 'HEALTHY';
    case DEGRADED  = 'DEGRADED';
    case FAULT     = 'FAULT';
    case CLOSED    = 'CLOSED';
    case CONNECTING = 'CONNECTING';
}
