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
 * Fluent builder for AsyncCacheConfig.
 */
final class AsyncCacheConfigBuilder
{
    private ?LimiterInterface $rate_limiter = null;
    private ?LoggerInterface $logger = null;
    private ?LockFactory $lock_factory = null;
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];
    private ?EventDispatcherInterface $dispatcher = null;
    private ?SerializerInterface $serializer = null;
    private ?ClockInterface $clock = null;

    public function __construct(
        private readonly PsrCacheInterface|ReactCacheInterface|AsyncCacheAdapterInterface $cache_adapter
    ) {}

    public function withRateLimiter(LimiterInterface $rate_limiter) : self
    {
        $this->rate_limiter = $rate_limiter;

        return $this;
    }

    public function withLogger(LoggerInterface $logger) : self
    {
        $this->logger = $logger;

        return $this;
    }

    public function withLockFactory(LockFactory $lock_factory) : self
    {
        $this->lock_factory = $lock_factory;

        return $this;
    }

    public function withMiddleware(MiddlewareInterface $middleware) : self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function withEventDispatcher(EventDispatcherInterface $dispatcher) : self
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    public function withSerializer(SerializerInterface $serializer) : self
    {
        $this->serializer = $serializer;

        return $this;
    }

    public function withClock(ClockInterface $clock) : self
    {
        $this->clock = $clock;

        return $this;
    }

    /**
     * Builds and returns the AsyncCacheConfig instance.
     */
    public function build() : AsyncCacheConfig
    {
        return new AsyncCacheConfig(
            $this->cache_adapter,
            $this->rate_limiter,
            $this->logger,
            $this->lock_factory,
            $this->dispatcher,
            $this->serializer,
            $this->clock,
            $this->middlewares
        );
    }
}
