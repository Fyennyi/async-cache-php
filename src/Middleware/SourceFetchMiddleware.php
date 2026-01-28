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

namespace Fyennyi\AsyncCache\Middleware;

use Fyennyi\AsyncCache\Bridge\PromiseAdapter;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Enum\CacheStatus;
use Fyennyi\AsyncCache\Event\CacheMissEvent;
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * Final middleware in the chain that executes the actual data-fetching factory
 */
class SourceFetchMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheStorage $storage,
        private LoggerInterface $logger,
        private ?EventDispatcherInterface $dispatcher = null
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $this->logger->debug('AsyncCache MISS: fetching from source', ['key' => $context->key]);
        $this->dispatcher?->dispatch(new CacheMissEvent($context->key));

        $start = microtime(true);
        $factory = $context->promise_factory;

        return PromiseAdapter::toPromise($factory())->then(
            function ($data) use ($context, $start) {
                $generation_time = microtime(true) - $start;
                $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Miss, microtime(true) - $context->start_time, $context->options->tags));

                // Background persistence
                $this->storage->set($context->key, $data, $context->options, $generation_time);

                return $data;
            }
        );
    }
}
