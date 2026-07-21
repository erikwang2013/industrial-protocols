<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Exception;

use Erikwang2013\IndustrialProtocols\Exception\ProtocolException;

class OpcUaException extends ProtocolException
{
    public static function fromStatusCode(int $statusCode): self
    {
        return new self("OPC UA error: 0x" . dechex($statusCode), ['status_code' => $statusCode]);
    }
}
