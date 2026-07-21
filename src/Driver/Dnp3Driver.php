<?php

namespace Erikwang2013\IndustrialProtocols\Dnp3\Driver;

use Erikwang2013\IndustrialProtocols\Dnp3\Exception\Dnp3Exception;
use Erikwang2013\IndustrialProtocols\Dnp3\Frame\Dnp3Frame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * DNP3 TCP stream driver.
 */
class Dnp3Driver implements DriverInterface
{
    /** @var resource|null */
    private $socket = null;
    private string $host;
    private int $port;
    private float $timeout;
    private float $latency = 0.0;

    /**
     * @param array{host?:string,port?:int,timeout?:float,serial_port?:string,baud_rate?:int} $config
     */
    public function __construct(array $config = [])
    {
        $this->host    = $config['host'] ?? '127.0.0.1';
        $this->port    = $config['port'] ?? 20000;
        $this->timeout = $config['timeout'] ?? 5.0;
    }

    public function connect(): void
    {
        $socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if (!$socket) {
            throw new Dnp3Exception("DNP3 connection failed: [$errno] $errstr");
        }

        stream_set_timeout($socket, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1e6));
        stream_set_blocking($socket, true);
        $this->socket = $socket;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function isConnected(): bool
    {
        return $this->socket !== null;
    }

    public function send(FrameInterface $frame): FrameInterface
    {
        if (!$this->socket) {
            throw new Dnp3Exception('Not connected');
        }

        $bytes = $frame->toBytes();
        $start = microtime(true);
        fwrite($this->socket, $bytes);

        // Read response
        $response = $this->readFrame();
        $this->latency = (microtime(true) - $start) * 1000;
        return $response;
    }

    public function sendAsync(FrameInterface $frame): mixed
    {
        return $this->send($frame);
    }

    public function getLatency(): float
    {
        return $this->latency;
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    /**
     * Read a DNP3 frame from the stream.
     * Starts with start bytes 0x0564, then length, then the rest.
     */
    private function readFrame(): Dnp3Frame
    {
        // Read start bytes
        $buf = '';
        while (strlen($buf) < 2) {
            $chunk = fread($this->socket, 2 - strlen($buf));
            if ($chunk === false || strlen($chunk) === 0) {
                throw new Dnp3Exception('Failed to read DNP3 start bytes');
            }
            $buf .= $chunk;
        }

        if (ord($buf[0]) !== Dnp3Frame::START_HI || ord($buf[1]) !== Dnp3Frame::START_LO) {
            throw new Dnp3Exception('Invalid DNP3 start bytes');
        }

        // Read length
        $lengthByte = fread($this->socket, 1);
        if ($lengthByte === false) {
            throw new Dnp3Exception('Failed to read DNP3 length');
        }
        $length = ord($lengthByte);

        // Read remaining bytes: length gives total user data, rest of link header (5 bytes after length) + user data
        // bytes already read: start(2) + length(1) = 3
        // Need: control(1) + dest(2) + src(2) + crc(2) + user_data(length)
        $remaining = 1 + 2 + 2 + 2 + $length - 5; // (control+dest+src+crc) = 7 minus length field = already counted
        $remaining = 7 + $length; // total after length byte

        $packetBody = $buf . $lengthByte;
        while (strlen($packetBody) < (10 + $length)) {
            $need = (10 + $length) - strlen($packetBody);
            $chunk = fread($this->socket, $need);
            if ($chunk === false || strlen($chunk) === 0) {
                throw new Dnp3Exception('Failed to read DNP3 frame body');
            }
            $packetBody .= $chunk;
        }

        return Dnp3Frame::fromBytes($packetBody);
    }
}
