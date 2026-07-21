<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Tests\Unit;

use Erikwang2013\IndustrialProtocols\OpcUa\Services\SessionManager;
use Erikwang2013\IndustrialProtocols\OpcUa\Transport\SecureChannel;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\NodeId;
use Erikwang2013\IndustrialProtocols\OpcUa\Types\StatusCode;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    public function testServiceConstants(): void
    {
        $this->assertSame(461, SecureChannel::SERVICE_CREATE_SESSION);
        $this->assertSame(467, SecureChannel::SERVICE_ACTIVATE_SESSION);
        $this->assertSame(631, SecureChannel::SERVICE_READ);
        $this->assertSame(673, SecureChannel::SERVICE_WRITE);
        $this->assertSame(527, SecureChannel::SERVICE_BROWSE);
    }

    public function testServiceConstantsAreDistinct(): void
    {
        $constants = [
            SecureChannel::SERVICE_CREATE_SESSION,
            SecureChannel::SERVICE_ACTIVATE_SESSION,
            SecureChannel::SERVICE_READ,
            SecureChannel::SERVICE_WRITE,
            SecureChannel::SERVICE_BROWSE,
            SecureChannel::SERVICE_OPEN_SECURE_CHANNEL,
            SecureChannel::SERVICE_CLOSE_SECURE_CHANNEL,
        ];

        $this->assertSame(
            count($constants),
            count(array_unique($constants)),
            'All service constants must be unique',
        );
    }

    public function testNodeIdOpcUaStandardTypes(): void
    {
        $root    = new NodeId(0, 84);    // Root folder
        $objects = new NodeId(0, 85);    // Objects folder
        $server  = new NodeId(0, 2253);  // Server object

        $this->assertSame('i=84', $root->toString());
        $this->assertSame('i=85', $objects->toString());
        $this->assertSame('i=2253', $server->toString());
    }

    public function testNodeIdStringIdentifier(): void
    {
        $ns0Str = new NodeId(0, 'TestNode');
        $this->assertSame('s=TestNode', $ns0Str->toString());

        $ns2Str = new NodeId(2, 'MyVar');
        $this->assertSame('ns=2;s=MyVar', $ns2Str->toString());
    }

    public function testNodeIdNumericWithNamespace(): void
    {
        $id = new NodeId(3, 1000);
        $this->assertSame('ns=3;i=1000', $id->toString());
    }

    public function testStatusCodeIsGood(): void
    {
        $good = new StatusCode(StatusCode::GOOD);
        $this->assertTrue($good->isGood());
        $this->assertFalse($good->isBad());
    }

    public function testStatusCodeIsBad(): void
    {
        $bad = new StatusCode(StatusCode::BAD_UNEXPECTED_ERROR);
        $this->assertFalse($bad->isGood());
        $this->assertTrue($bad->isBad());
    }

    public function testStatusCodeBadNodeIdUnknown(): void
    {
        $badNodeId = new StatusCode(StatusCode::BAD_NODE_ID_UNKNOWN);
        $this->assertTrue($badNodeId->isBad());
        $this->assertSame(StatusCode::BAD_NODE_ID_UNKNOWN, $badNodeId->code);
    }
}
