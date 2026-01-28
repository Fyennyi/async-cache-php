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
use Fyennyi\AsyncCache\Event\CacheHitEvent;
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * Core middleware for initial cache retrieval and freshness validation
 */
class CacheLookupMiddleware implements MiddlewareInterface
{
    /**
     * @param  CacheStorage                   $storage     The cache interaction layer
     * @param  LoggerInterface                $logger      Logging implementation
     * @param  EventDispatcherInterface|null  $dispatcher  Event dispatcher for telemetry
     */
    public function __construct(
        private CacheStorage $storage,
        private LoggerInterface $logger,
        private ?EventDispatcherInterface $dispatcher = null
    ) {
    }

    /**
     * Performs initial cache lookup and handles freshness validation
     *
     * @param  CacheContext  $context  The resolution state
     * @param  callable      $next     Next handler in the chain
     * @return PromiseInterface        Promise resolving to cached or fresh data
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        // Basic tracing for debugging
        $this->logger->debug('CacheLookupMiddleware: handling cache context', ['key' => $context->key, 'strategy' => $context->options->strategy->value]);
        if ($context->options->strategy === CacheStrategy::ForceRefresh) {
            $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Bypass, 0, $context->options->tags));
            return $next($context);
        }

        $promise = $this->storage->get($context->key, $context->options)->then(
            function ($cached_item) use ($context, $next) {
                if ($cached_item instanceof CachedItem) {
                    $context->stale_item = $cached_item;
                    $is_fresh = $cached_item->isFresh();

                    if ($is_fresh && $context->options->x_fetch_beta > 0 && $cached_item->generation_time > 0) {
                        $rand = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
                        $check = time() - ($cached_item->generation_time * $context->options->x_fetch_beta * log($rand));

                        if ($check > $cached_item->logical_expire_time) {
                            $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::XFetch, microtime(true) - $context->start_time, $context->options->tags));
                            $is_fresh = false;
                        }
                    }

                    if ($is_fresh) {
                        $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Hit, microtime(true) - $context->start_time, $context->options->tags));
                        $this->dispatcher?->dispatch(new CacheHitEvent($context->key, $cached_item->data));

                        // If item has tags, we MUST continue to TagValidationMiddleware
                        if (! empty($cached_item->tag_versions)) {
                            return $next($context);
                        }

                        return \React\Promise\resolve($cached_item->data);
                    }

                    if ($context->options->strategy === CacheStrategy::Background) {
                        $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Stale, microtime(true) - $context->start_time, $context->options->tags));
                        $this->dispatcher?->dispatch(new CacheHitEvent($context->key, $cached_item->data));

                        // Background fetch - catch errors to prevent unhandled rejection since this promise is not returned
                        $next($context)->catch(function(\Throwable $e) {
                            $this->logger->error('AsyncCache BACKGROUND_FETCH_ERROR: {msg}', ['key' => $context->key, 'msg' => $e->getMessage()]);
                        });

                        return \React\Promise\resolve($cached_item->data);
                    }
                }

                return $next($context);
            },
            function (\Throwable $e) use ($context, $next) {
                $this->logger->error('AsyncCache CACHE_LOOKUP_ERROR: {msg}', ['key' => $context->key, 'msg' => $e->getMessage()]);
                return $next($context);
            }
        );

        $promise->catch(function(\Throwable $e) use ($context) {
            $this->logger->debug('AsyncCache LOOKUP_PIPELINE_ERROR: {msg}', ['key' => $context->key, 'msg' => $e->getMessage()]);
        });

        return $promise;
    }
}
