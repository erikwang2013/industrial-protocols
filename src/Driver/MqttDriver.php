<?php

namespace Erikwang2013\IndustrialProtocols\Mqtt\Driver;

use Erikwang2013\IndustrialProtocols\Mqtt\Exception\MqttException;
use Erikwang2013\IndustrialProtocols\Mqtt\Frame\MqttFrame;
use Erikwang2013\IndustrialProtocols\Protocol\DriverInterface;
use Erikwang2013\IndustrialProtocols\Protocol\FrameInterface;

/**
 * MQTT TCP stream socket driver.
 *
 * Handles CONNECT handshake, PUBLISH, SUBSCRIBE, PING keep-alive.
 */
class MqttDriver implements DriverInterface
{
    /** @var resource|null */
    private $socket = null;
    private string $host;
    private int $port;
    private float $timeout;
    private string $clientId;
    private ?string $username;
    private ?string $password;
    private int $keepAlive;
    private float $lastPing = 0.0;
    private float $latency = 0.0;

    /**
     * @param array{host?:string,port?:int,timeout?:float,client_id?:string,username?:string,password?:string,keep_alive?:int} $config
     */
    public function __construct(array $config = [])
    {
        $this->host      = $config['host'] ?? '127.0.0.1';
        $this->port      = $config['port'] ?? 1883;
        $this->timeout   = $config['timeout'] ?? 5.0;
        $this->clientId  = $config['client_id'] ?? ('php-mqtt-' . bin2hex(random_bytes(8)));
        $this->username  = $config['username'] ?? null;
        $this->password  = $config['password'] ?? null;
        $this->keepAlive = $config['keep_alive'] ?? 60;
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
            throw new MqttException("MQTT connection failed: [$errno] $errstr");
        }

        stream_set_timeout($socket, (int) $this->timeout, (int) (($this->timeout - (int) $this->timeout) * 1e6));
        stream_set_blocking($socket, true);
        $this->socket = $socket;

        // CONNECT handshake
        $connectFrame = MqttFrame::connect($this->clientId, $this->username, $this->password, $this->keepAlive);
        $start = microtime(true);
        $response = $this->send($connectFrame);
        $this->latency = (microtime(true) - $start) * 1000;

        if (!$response->isConnAck()) {
            throw new MqttException('Expected CONNACK, got ' . $response->getTypeName());
        }

        $returnCode = $response->getReturnCode();
        if ($returnCode !== 0) {
            $msg = match ($returnCode) {
                1 => 'Connection refused: unacceptable protocol version',
                2 => 'Connection refused: identifier rejected',
                3 => 'Connection refused: server unavailable',
                4 => 'Connection refused: bad username or password',
                5 => 'Connection refused: not authorized',
                default => "Connection refused: code $returnCode",
            };
            throw new MqttException($msg);
        }

        $this->lastPing = time();
    }

    public function disconnect(): void
    {
        if ($this->socket) {
            try {
                $disconnect = MqttFrame::disconnect();
                fwrite($this->socket, $disconnect->toBytes());
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
            throw new MqttException('Not connected');
        }

        fwrite($this->socket, $frame->toBytes());

        // No response needed for some packets
        if ($frame->getType() === MqttFrame::TYPE_DISCONNECT) {
            return new MqttFrame(0);
        }
        if ($frame->getType() === MqttFrame::TYPE_PINGREQ) {
            $this->lastPing = time();
        }

        if ($frame->isPublish() && $frame->getQos() === 0) {
            return new MqttFrame(0); // No ack for QoS 0
        }

        return $this->readFrame();
    }

    public function sendAsync(FrameInterface $frame): mixed
    {
        return $this->send($frame); // Synchronous fallback
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
     * Subscribe to a topic and return the SUBACK frame.
     */
    public function subscribe(string $topic, int $qos = 0): MqttFrame
    {
        $frame = MqttFrame::subscribe([$topic => $qos]);
        return $this->send($frame);
    }

    /**
     * Unsubscribe from a topic.
     */
    public function unsubscribe(string $topic): MqttFrame
    {
        $frame = MqttFrame::unsubscribe([$topic]);
        return $this->send($frame);
    }

    /**
     * Publish a message to a topic.
     */
    public function publish(string $topic, string $payload, int $qos = 0, bool $retain = false): MqttFrame
    {
        $frame = MqttFrame::publish($topic, $payload, $qos, $retain);
        return $this->send($frame);
    }

    /**
     * Send PINGREQ and wait for PINGRESP.
     */
    public function ping(): void
    {
        $start = microtime(true);
        $this->send(MqttFrame::pingReq());
        $response = $this->readFrame();
        $this->latency = (microtime(true) - $start) * 1000;
        $this->lastPing = time();
    }

    /**
     * Keep-alive check. Call periodically.
     */
    public function keepAlive(): void
    {
        if ($this->socket && (time() - $this->lastPing) > ($this->keepAlive / 2)) {
            $this->ping();
        }
    }

    private function readFrame(): MqttFrame
    {
        $header = fread($this->socket, 1);
        if ($header === false || strlen($header) < 1) {
            throw new MqttException('Failed to read MQTT packet header');
        }

        $offset = 0;
        $body = '';
        // Read remaining length bytes
        do {
            $byte = fread($this->socket, 1);
            if ($byte === false || strlen($byte) < 1) {
                throw new MqttException('Failed to read MQTT remaining length');
            }
            $body .= $byte;
            $offset++;
        } while (ord($byte) & 0x80);

        $rlOffset = 0;
        $remaining = MqttFrame::decodeRemainingLength($body, $rlOffset);

        $packetBody = '';
        while (strlen($packetBody) < $remaining) {
            $chunk = fread($this->socket, $remaining - strlen($packetBody));
            if ($chunk === false || strlen($chunk) === 0) {
                throw new MqttException('Failed to read MQTT packet body');
            }
            $packetBody .= $chunk;
        }

        $fullPacket = $header . $body . $packetBody;
        return MqttFrame::fromBytes($fullPacket);
    }
}
