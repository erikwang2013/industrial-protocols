<?php
namespace Erikwang2013\IndustrialProtocols\Alert;

class WebhookAlertChannel implements AlertChannelInterface
{
    public function __construct(private string $url, private int $timeout = 5) {}

    public function send(string $title, string $message, string $level = 'info'): void
    {
        $payload = json_encode(['title' => $title, 'message' => $message, 'level' => $level, 'timestamp' => date('c')]);
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => $this->timeout,
        ]]);
        @file_get_contents($this->url, false, $ctx);
    }
}
