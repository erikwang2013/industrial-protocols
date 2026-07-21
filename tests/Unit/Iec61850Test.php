<?php

namespace Erikwang2013\IndustrialProtocols\Iec61850\Tests\Unit;

use Erikwang2013\IndustrialProtocols\Iec61850\Frame\Iec61850Frame;
use PHPUnit\Framework\TestCase;

class Iec61850Test extends TestCase
{
    public function test_parse_data_path_full(): void
    {
        $parsed = Iec61850Frame::parseDataPath('IED1/MMXU1.MX.A.phsA');

        $this->assertSame('IED1', $parsed['ld']);
        $this->assertSame('MMXU1', $parsed['ln']);
        $this->assertSame('MX', $parsed['fc']);
        $this->assertSame('A', $parsed['do']);
        $this->assertSame('phsA', $parsed['da']);
    }

    public function test_parse_data_path_no_da(): void
    {
        $parsed = Iec61850Frame::parseDataPath('IED1/LLN0.OR.Health');

        $this->assertSame('IED1', $parsed['ld']);
        $this->assertSame('LLN0', $parsed['ln']);
        $this->assertSame('OR', $parsed['fc']);
        $this->assertSame('Health', $parsed['do']);
        $this->assertSame('', $parsed['da']);
    }

    public function test_parse_data_path_simple(): void
    {
        $parsed = Iec61850Frame::parseDataPath('IED1/XCBR1');

        $this->assertSame('IED1', $parsed['ld']);
        $this->assertSame('XCBR1', $parsed['ln']);
        $this->assertSame('', $parsed['fc']);
    }

    public function test_build_data_path(): void
    {
        $path = Iec61850Frame::buildDataPath([
            'ld' => 'IED1',
            'ln' => 'MMXU1',
            'fc' => 'MX',
            'do' => 'A',
            'da' => 'phsA',
        ]);

        $this->assertSame('IED1/MMXU1.MX.A.phsA', $path);
    }

    public function test_initiate_request(): void
    {
        $frame = Iec61850Frame::initiateRequest(65000, 5);

        $this->assertSame(Iec61850Frame::PDU_INITIATE_REQUEST, $frame->getPduType());
        $this->assertSame('INITIATE_REQUEST', $frame->getPduName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);

        // TPKT version is 3
        $this->assertSame(3, ord($bytes[0]));
    }

    public function test_conclude(): void
    {
        $frame = Iec61850Frame::conclude();

        $this->assertSame(Iec61850Frame::PDU_CONCLUDE_REQUEST, $frame->getPduType());
        $this->assertSame('CONCLUDE_REQUEST', $frame->getPduName());

        $bytes = $frame->toBytes();
        $this->assertNotEmpty($bytes);
    }

    public function test_read_request(): void
    {
        $frame = Iec61850Frame::readRequest('IED1/MMXU1.MX.A.phsA');

        $this->assertSame(Iec61850Frame::PDU_READ_REQUEST, $frame->getPduType());
        $this->assertSame('READ_REQUEST', $frame->getPduName());

        $data = $frame->getData();
        $this->assertSame('IED1/MMXU1.MX.A.phsA', $data['data']['data_path']);
    }

    public function test_write_request(): void
    {
        $frame = Iec61850Frame::writeRequest('IED1/XCBR1.ST.Pos.stVal', true);

        $this->assertSame(Iec61850Frame::PDU_WRITE_REQUEST, $frame->getPduType());
        $this->assertSame('WRITE_REQUEST', $frame->getPduName());

        $data = $frame->getData();
        $this->assertSame(true, $data['data']['value']);
    }

    public function test_tpkt_header(): void
    {
        $frame = Iec61850Frame::readRequest('IED1/LLN0.OR.Health.stVal');
        $bytes = $frame->toBytes();

        // TPKT: version=3, reserved=0, length (big-endian)
        $this->assertSame("\x03", $bytes[0]);
        $this->assertSame("\x00", $bytes[1]);

        $tpktLen = unpack('n', substr($bytes, 2, 2))[1];
        $this->assertSame(strlen($bytes), $tpktLen);
    }

    public function test_encode_decode_roundtrip(): void
    {
        $frame = Iec61850Frame::readRequest('IED1/MMXU1.MX.A.phsA', 42);
        $bytes = $frame->toBytes();

        $decoded = Iec61850Frame::fromBytes($bytes);
        $this->assertSame(Iec61850Frame::PDU_READ_REQUEST, $decoded->getPduType());
        $this->assertSame(42, $decoded->getInvokeId());
    }

    public function test_get_data_returns_array(): void
    {
        $frame = Iec61850Frame::readRequest('Test/LLN0');
        $data = $frame->getData();

        $this->assertIsArray($data);
        $this->assertSame('READ_REQUEST', $data['pdu_type']);
        $this->assertSame('Test/LLN0', $data['data']['data_path']);
    }
}
