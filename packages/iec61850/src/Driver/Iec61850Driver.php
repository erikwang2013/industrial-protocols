<?php

namespace Erikwang2013\IndustrialProtocols\Iec61850\Driver;

use Erikwang2013\IndustrialProtocols\Iec61850\Exception\Iec61850Exception;
use Erikwang2013\IndustrialProtocols\Iec61850\Frame\Iec61850Frame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * IEC 61850 MMS TCP driver (port 102).
 */
class Iec61850Driver implements DriverInterface
{
    /** @var resource|null */
    private $socket = null;
    private string $host;
    private int $port;
    private float $timeout;
    private float $latency = 0.0;

    /**
     * @param array{host?:string,port?:int,timeout?:float} $config
     */
    public function __construct(array $config = [])
    {
        $this->host    = $config['host'] ?? '127.0.0.1';
        $this->port    = $config['port'] ?? 102;
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
            throw new Iec61850Exception("IEC 61850 connection failed: [$errno] $errstr");
        }

        stream_set_timeout($socket, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1e6));
        stream_set_blocking($socket, true);
        $this->socket = $socket;

        // Initiate MMS session
        $start = microtime(true);
        $initiate = Iec61850Frame::initiateRequest();
        $this->sendRaw($initiate);
        $this->latency = (microtime(true) - $start) * 1000;
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            try {
                $conclude = Iec61850Frame::conclude();
                $this->sendRaw($conclude);
            } catch (\Throwable) {}
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
            throw new Iec61850Exception('Not connected');
        }

        $start = microtime(true);
        fwrite($this->socket, $frame->toBytes());
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
     * Send without waiting for response (for fire-and-forget operations).
     */
    public function sendRaw(FrameInterface $frame): void
    {
        if (!$this->socket) {
            throw new Iec61850Exception('Not connected');
        }
        fwrite($this->socket, $frame->toBytes());
    }

    private function readFrame(): Iec61850Frame
    {
        // Read TPKT header (4 bytes)
        $header = '';
        while (strlen($header) < 4) {
            $chunk = fread($this->socket, 4 - strlen($header));
            if ($chunk === false || strlen($chunk) === 0) {
                throw new Iec61850Exception('Failed to read TPKT header');
            }
            $header .= $chunk;
        }

        $tpktLen = unpack('n', substr($header, 2, 2))[1];
        $remaining = $tpktLen - 4;

        $body = '';
        while (strlen($body) < $remaining) {
            $chunk = fread($this->socket, $remaining - strlen($body));
            if ($chunk === false || strlen($chunk) === 0) {
                throw new Iec61850Exception('Failed to read MMS body');
            }
            $body .= $chunk;
        }

        return Iec61850Frame::fromBytes($header . $body);
    }
}
