<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

namespace Erikwang2013\IndustrialProtocols;

use Erikwang2013\IndustrialProtocols\Config\ConfigRepositoryInterface;
use Erikwang2013\IndustrialProtocols\Config\FileConfigRepository;
use Erikwang2013\IndustrialProtocols\Connection\ConnectionManager;
use Erikwang2013\IndustrialProtocols\Connection\Strategy\LazyStrategy;
use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineFactory;
use Erikwang2013\IndustrialProtocols\Coroutine\CoroutineAdapterInterface;
use Erikwang2013\IndustrialProtocols\Event\KernelBootedEvent;
use Erikwang2013\IndustrialProtocols\Framework\FrameworkAdapterInterface;
use Erikwang2013\IndustrialProtocols\Framework\LaravelAdapter;
use Erikwang2013\IndustrialProtocols\Framework\PlainPhpAdapter;
use Erikwang2013\IndustrialProtocols\Framework\ThinkPHPAdapter;
use Erikwang2013\IndustrialProtocols\Framework\WebmanAdapter;
use Erikwang2013\IndustrialProtocols\Framework\Yii2Adapter;
use Erikwang2013\IndustrialProtocols\Log\LogDriverInterface;
use Erikwang2013\IndustrialProtocols\Log\PsrLogDriver;
use Erikwang2013\IndustrialProtocols\Protocol\ProtocolRegistry;
use Erikwang2013\IndustrialProtocols\Vendor\DefaultVendors;
use Erikwang2013\IndustrialProtocols\Vendor\VendorBridgeFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

class Kernel
{
    private ProtocolRegistry $protocolRegistry;
    private ConnectionManager $connectionManager;
    private ConfigRepositoryInterface $configRepository;
    private CoroutineAdapterInterface $coroutine;
    private LogDriverInterface $log;
    private FrameworkAdapterInterface $framework;
    private ?VendorBridgeFactory $vendorBridgeFactory = null;
    private bool $booted = false;

    public function __construct(
        private array $options = [],
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->protocolRegistry = new ProtocolRegistry();
        $this->coroutine = CoroutineFactory::create();
        $this->log = new PsrLogDriver(new NullLogger());
    }

    public function boot(): void
    {
        $configPath = $this->options['config_path']
            ?? dirname(__DIR__) . '/config/industrial-protocols.php';

        $this->configRepository = new FileConfigRepository($configPath);
        $this->framework = $this->detectFramework();
        $this->framework->registerConfig();
        $this->framework->registerServices();
        $this->framework->registerCommands();

        $this->connectionManager = new ConnectionManager(
            $this->protocolRegistry->all(),
            $this->configRepository,
            $this->eventDispatcher ?? new class implements EventDispatcherInterface {
                public function dispatch(object $event): object { return $event; }
            },
            $this->coroutine,
            $this->log,
            new LazyStrategy(),
        );

        $this->booted = true;

        if ($this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new KernelBootedEvent(
                array_keys($this->protocolRegistry->all()),
                $this->framework->getName(),
            ));
        }
    }

    public function shutdown(): void
    {
        $this->connectionManager?->shutdown();
        $this->booted = false;
    }

    public function getConnectionManager(): ConnectionManager
    {
        $this->ensureBooted();
        return $this->connectionManager;
    }

    public function getProtocolRegistry(): ProtocolRegistry
    {
        return $this->protocolRegistry;
    }

    public function getConfigRepository(): ConfigRepositoryInterface
    {
        $this->ensureBooted();
        return $this->configRepository;
    }

    public function getCoroutineAdapter(): CoroutineAdapterInterface
    {
        return $this->coroutine;
    }

    public function getLogDriver(): LogDriverInterface
    {
        return $this->log;
    }

    public function getFramework(): FrameworkAdapterInterface
    {
        return $this->framework;
    }

    public function getVendorBridgeFactory(): VendorBridgeFactory
    {
        if ($this->vendorBridgeFactory === null) {
            $this->vendorBridgeFactory = new VendorBridgeFactory();
            DefaultVendors::register($this->vendorBridgeFactory);
        }
        return $this->vendorBridgeFactory;
    }

    private function detectFramework(): FrameworkAdapterInterface
    {
        $adapters = [
            new LaravelAdapter(),
            new WebmanAdapter(),
            new ThinkPHPAdapter(),
            new Yii2Adapter(),
            new PlainPhpAdapter(
                $this->options['config_path']
                ?? dirname(__DIR__) . '/config/industrial-protocols.php'
            ),
        ];

        foreach ($adapters as $adapter) {
            if ($adapter->detect()) {
                return $adapter;
            }
        }

        return new PlainPhpAdapter(
            $this->options['config_path']
            ?? dirname(__DIR__) . '/config/industrial-protocols.php'
        );
    }

    private function ensureBooted(): void
    {
        if (!$this->booted) {
            throw new \RuntimeException('Kernel must be booted before using. Call boot() first.');
        }
    }
}
