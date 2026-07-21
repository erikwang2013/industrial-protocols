<?php

declare(strict_types=1);

namespace Erikwang2013\IndustrialProtocols\OpcUa\Driver;

use Erikwang2013\IndustrialProtocols\OpcUa\Transport\SecureChannel;
use Erikwang2013\IndustrialProtocols\OpcUa\Transport\UaTcpMessage;

/**
 * OPC UA TCP Driver.
 *
 * Connects to an OPC UA server via TCP, performs the Hello/Acknowledge
 * handshake, and opens a Secure Channel.
 */
class OpcUaDriver
{
    /** @var resource|null */
    private $socket = null;

    private ?SecureChannel $channel = null;

    /**
     * @param string $host        Server hostname or IP
     * @param int    $port        Server port (default 4840)
     * @param float  $timeout     Connection timeout in seconds
     * @param string $endpointUrl Override endpoint URL (auto-generated if empty)
     */
    public function __construct(
        private string $host,
        private int $port = 4840,
        private float $timeout = 5.0,
        private string $endpointUrl = '',
    ) {}

    /**
     * Connect to the OPC UA server:
     * 1. Open TCP socket
     * 2. Send Hello, read Acknowledge
     * 3. Open Secure Channel
     *
     * @throws \RuntimeException on connection or protocol failure
     */
    public function connect(): void
    {
        $errno = 0;
        $errstr = '';

        $this->socket = @stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if (!$this->socket) {
            throw new \RuntimeException(
                "OPC UA connect to {$this->host}:{$this->port} failed: [$errno] $errstr"
            );
        }

        stream_set_timeout($this->socket, (int)$this->timeout, 0);

        // Build endpoint URL
        $endpointUrl = $this->endpointUrl !== '' && $this->endpointUrl !== '0'
            ? $this->endpointUrl
            : "opc.tcp://{$this->host}:{$this->port}";

        // --- TCP Hello/Acknowledge handshake ---
        $hello = UaTcpMessage::buildHello($endpointUrl);
        $written = @fwrite($this->socket, $hello);
        if ($written === false) {
            throw new \RuntimeException('OPC UA Hello write failed');
        }

        $ackBytes = @fread($this->socket, 28);
        if ($ackBytes === false || strlen($ackBytes) < 28) {
            throw new \RuntimeException('Failed to read OPC UA Acknowledge');
        }
        UaTcpMessage::parseAcknowledge($ackBytes);

        // --- Open Secure Channel ---
        $this->channel = new SecureChannel($this->socket);
        $this->channel->open();
    }

    /**
     * Disconnect: close Secure Channel and TCP socket.
     */
    public function disconnect(): void
    {
        if ($this->channel !== null) {
            try {
                $this->channel->close();
            } catch (\Throwable) {
                // Best-effort close
            }
            $this->channel = null;
        }

        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * Check whether the driver is connected.
     */
    public function isConnected(): bool
    {
        return $this->socket !== null && $this->channel !== null;
    }

    /**
     * Get the active Secure Channel.
     *
     * @throws \RuntimeException if not connected
     */
    public function getSecureChannel(): SecureChannel
    {
        if ($this->channel === null) {
            throw new \RuntimeException('Not connected — call connect() first');
        }
        return $this->channel;
    }
}
