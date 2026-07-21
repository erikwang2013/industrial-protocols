<?php

namespace Erikwang2013\IndustrialProtocols\Bridge;

class ExternalProcessBridge implements BridgeInterface
{
    private $process = null;
    private array $pipes = [];
    private bool $ready = false;

    /**
     * @param string $executable Path to the C/C++ SDK executable or script
     * @param string $workDir Working directory for the process
     * @param array $env Extra environment variables
     */
    public function __construct(
        private string $executable,
        private string $workDir = '',
        private array $env = [],
        private float $startupTimeout = 10.0,
    ) {}

    public function open(): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $env = array_merge($_ENV ?? [], $this->env);
        $cwd = $this->workDir ?: dirname($this->executable);

        $cmd = $this->executable;
        if (str_starts_with($cmd, './') || str_starts_with($cmd, '/')) {
            $cmd = escapeshellcmd($cmd);
        }

        $this->process = proc_open($cmd, $descriptors, $this->pipes, $cwd, $env);

        if (!is_resource($this->process)) {
            throw new \RuntimeException("Failed to start bridge process: {$this->executable}");
        }

        // Set non-blocking for startup check
        stream_set_blocking($this->pipes[1], false);
        $deadline = time() + $this->startupTimeout;
        $buffer = '';

        while (time() < $deadline) {
            $read = [$this->pipes[1]];
            $write = null;
            $except = null;
            if (stream_select($read, $write, $except, 0, 200000)) {
                $chunk = fread($this->pipes[1], 4096);
                if ($chunk) {
                    $buffer .= $chunk;
                }
            }
            if (str_contains($buffer, 'READY') || str_contains($buffer, 'started')) {
                $this->ready = true;
                break;
            }
            usleep(50000);
        }

        if (!$this->ready) {
            // Assume ready if process is still running
            $status = proc_get_status($this->process);
            $this->ready = $status['running'] ?? false;
        }

        stream_set_blocking($this->pipes[1], true);
    }

    public function close(): void
    {
        if ($this->process) {
            if (isset($this->pipes[0])) {
                fclose($this->pipes[0]);
            }
            if (isset($this->pipes[1])) {
                fclose($this->pipes[1]);
            }
            if (isset($this->pipes[2])) {
                fclose($this->pipes[2]);
            }
            proc_terminate($this->process, SIGTERM);
            proc_close($this->process);
            $this->process = null;
            $this->ready = false;
        }
    }

    public function execute(string $command, string|array $data = ''): string
    {
        if (!$this->ready) {
            throw new \RuntimeException('Bridge not ready');
        }

        $input = is_array($data) ? json_encode($data) : $data;
        $line = $command . ' ' . $input . "\n";
        fwrite($this->pipes[0], $line);

        $response = fgets($this->pipes[1]);
        if ($response === false) {
            throw new \RuntimeException('Bridge process closed unexpectedly');
        }

        return trim($response);
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getType(): string
    {
        return 'external-process';
    }
}
