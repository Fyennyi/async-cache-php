<?php

/*
 *
 *     _                          ____           _            ____  _   _ ____
 *    / \   ___ _   _ _ __   ___ / ___|__ _  ___| |__   ___  |  _ \| | | |  _ \
 *   / _ \ / __| | | | '_ \ / __| |   / _` |/ __| '_ \ / _ \ | |_) | |_| | |_) |
 *  / ___ \\__ \ |_| | | | | (__| |__| (_| | (__| | | |  __/ |  __/|  _  |  __/
 * /_/   \_\___/\__, |_| |_|\___|\____\__,_|\___|_| |_|\___| |_|   |_| |_|_|
 *              |___/
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author Serhii Cherneha
 * @link https://chernega.eu.org/
 *
 *
 */

namespace Fyennyi\AsyncCache\Config;

use Fyennyi\AsyncCache\Middleware\MiddlewareInterface;
use Fyennyi\AsyncCache\Serializer\SerializerInterface;
use Fyennyi\AsyncCache\Storage\AsyncCacheAdapterInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use React\Cache\CacheInterface as ReactCacheInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\LimiterInterface;

/**
 * Configuration object for AsyncCacheManager.
 *
 * Encapsulates all configuration parameters to keep the constructor clean.
 * Use the fluent builder API to configure.
 */
final class AsyncCacheConfig
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    public function __construct(
        private readonly PsrCacheInterface|ReactCacheInterface|AsyncCacheAdapterInterface $cache_adapter,
        private readonly ?LimiterInterface $rate_limiter = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?LockFactory $lock_factory = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?SerializerInterface $serializer = null,
        private readonly ?ClockInterface $clock = null,
        array $middlewares = []
    ) {
        $this->middlewares = $middlewares;
    }

    public function getCacheAdapter(): PsrCacheInterface|ReactCacheInterface|AsyncCacheAdapterInterface
    {
        return $this->cache_adapter;
    }

    public function getRateLimiter(): ?LimiterInterface
    {
        return $this->rate_limiter;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function getLockFactory(): ?LockFactory
    {
        return $this->lock_factory;
    }

    public function getDispatcher(): ?EventDispatcherInterface
    {
        return $this->dispatcher;
    }

    public function getSerializer(): ?SerializerInterface
    {
        return $this->serializer;
    }

    public function getClock(): ?ClockInterface
    {
        return $this->clock;
    }

    /**
     * @return MiddlewareInterface[]
     */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * Creates a new configuration builder.
     */
    public static function builder(PsrCacheInterface|ReactCacheInterface|AsyncCacheAdapterInterface $cache_adapter): AsyncCacheConfigBuilder
    {
        return new AsyncCacheConfigBuilder($cache_adapter);
    }
}
