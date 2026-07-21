<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols\Bridge;

class TcpGatewayBridge implements BridgeInterface
{
    private $socket = null;

    /**
     * @param string $host Gateway IP
     * @param int $port Gateway port
     * @param float $timeout Connection timeout in seconds
     * @param string $protocol 'tcp' or 'udp'
     */
    public function __construct(
        private string $host,
        private int $port,
        private float $timeout = 5.0,
        private string $protocol = 'tcp',
    ) {}

    public function open(): void
    {
        $this->socket = @stream_socket_client(
            "{$this->protocol}://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            $this->timeout,
        );
        if (!$this->socket) {
            throw new \RuntimeException("Gateway bridge connect failed: [$errno] $errstr");
        }
        stream_set_timeout($this->socket, (int) $this->timeout);
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    public function execute(string $command, string|array $data = ''): string
    {
        if (!$this->socket) {
            throw new \RuntimeException('Gateway bridge not connected');
        }

        $payload = is_array($data) ? json_encode($data) : $data;
        $request = pack('v', strlen($command)) . $command
                 . pack('V', strlen($payload)) . $payload;

        fwrite($this->socket, $request);
        $response = fread($this->socket, 65535);

        if ($response === false || $response === '') {
            throw new \RuntimeException('Gateway bridge read timeout');
        }
        return $response;
    }

    public function isReady(): bool
    {
        return $this->socket !== null;
    }

    public function getType(): string
    {
        return 'tcp-gateway';
    }
}
