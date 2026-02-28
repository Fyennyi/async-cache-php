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

use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Enum\CacheStatus;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use Fyennyi\AsyncCache\Event\CacheMissEvent;
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * Core middleware for initial cache retrieval and freshness validation.
 */
class CacheLookupMiddleware implements MiddlewareInterface
{
    /**
     * @param CacheStorage                  $storage    The cache interaction layer
     * @param LoggerInterface               $logger     Logging implementation
     * @param EventDispatcherInterface|null $dispatcher Event dispatcher for telemetry
     */
    public function __construct(
        private CacheStorage $storage,
        private LoggerInterface $logger,
        private ?EventDispatcherInterface $dispatcher = null
    ) {}

    /**
     * Performs initial cache lookup and handles freshness validation.
     *
     * @template T
     *
     * @param  callable(CacheContext):PromiseInterface<T> $next
     * @return PromiseInterface<T>
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $this->logger->debug('AsyncCache LOOKUP_START: Beginning cache retrieval', ['key' => $context->key, 'strategy' => $context->options->strategy->value]);

        if (CacheStrategy::ForceRefresh === $context->options->strategy) {
            $this->logger->debug('AsyncCache LOOKUP_BYPASS: Strategy is ForceRefresh, bypassing cache', ['key' => $context->key]);
            $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Bypass, 0, $context->options->tags, (float) $context->clock->now()->format('U.u')));

            return $next($context);
        }

        return $this->storage->get($context->key, $context->options)->then(
            function ($cached_item) use ($context, $next) {
                if (! ($cached_item instanceof CachedItem)) {
                    $this->logger->debug('AsyncCache LOOKUP_MISS: Item not found in cache', ['key' => $context->key]);
                    $this->dispatcher?->dispatch(new CacheMissEvent($context->key, (float) $context->clock->now()->format('U.u')));

                    return $next($context);
                }

                $context->stale_item = $cached_item;
                $now_ts = $context->clock->now()->getTimestamp();
                $is_fresh = $cached_item->isFresh($now_ts);

                if ($is_fresh && $context->options->x_fetch_beta > 0 && $cached_item->generation_time > 0) {
                    $rand = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
                    $check = $now_ts - ($cached_item->generation_time * $context->options->x_fetch_beta * log($rand));

                    if ($check > $cached_item->logical_expire_time) {
                        $this->logger->debug('AsyncCache LOOKUP_XFETCH: Probabilistic early expiration triggered', ['key' => $context->key]);
                        $now = (float) $context->clock->now()->format('U.u');
                        $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::XFetch, $context->getElapsedTime(), $context->options->tags, $now));

                        // Create a stale version of the item by setting logical_expire_time to current time - 1
                        $context->stale_item = new CachedItem(
                            $cached_item->data,
                            $now_ts - 1,
                            $cached_item->version,
                            $cached_item->is_compressed,
                            $cached_item->generation_time,
                            $cached_item->tag_versions
                        );
                        $is_fresh = false;
                    }
                }

                if ($is_fresh) {
                    $this->logger->debug('AsyncCache LOOKUP_FRESH: Fresh item found in cache', ['key' => $context->key]);
                } else {
                    $this->logger->debug('AsyncCache LOOKUP_STALE: Stale item found in cache', ['key' => $context->key]);
                }

                return $next($context);
            },
            function (\Throwable $e) use ($context, $next) {
                $this->logger->error('AsyncCache LOOKUP_ERROR: Cache retrieval failed', ['key' => $context->key, 'error' => $e->getMessage()]);

                return $next($context);
            }
        );
    }
}
