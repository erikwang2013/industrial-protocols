<?php
namespace IndustrialProtocols\Alert;

class AlertManager
{
    /** @var AlertChannelInterface[] */
    private array $channels = [];

    public function addChannel(string $name, AlertChannelInterface $channel): void
    {
        $this->channels[$name] = $channel;
    }

    public function send(string $title, string $message, string $level = 'info'): void
    {
        foreach ($this->channels as $channel) {
            try {
                $channel->send($title, $message, $level);
            } catch (\Throwable $e) {
                // Alert delivery failure should not crash the application
            }
        }
    }

    /** @return AlertChannelInterface[] */
    public function getChannels(): array
    {
        return $this->channels;
    }
}
