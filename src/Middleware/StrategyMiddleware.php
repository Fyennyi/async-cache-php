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
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

use function React\Promise\resolve;

/**
 * Single source of truth for caching strategy decisions.
 */
class StrategyMiddleware implements MiddlewareInterface
{
    /**
     * @param LoggerInterface               $logger     Logging implementation
     * @param EventDispatcherInterface|null $dispatcher Event dispatcher for telemetry
     */
    public function __construct(
        private LoggerInterface $logger,
        private ?EventDispatcherInterface $dispatcher = null
    ) {}

    /**
     * @template T
     *
     * @param  CacheContext                               $context
     * @param  callable(CacheContext):PromiseInterface<T> $next
     * @return PromiseInterface<T>
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $stale_item = $context->stale_item;

        if (null === $stale_item) {
            $this->logger->debug('AsyncCache STRATEGY_MISS: No cached item found, proceeding to fetch', ['key' => $context->key]);

            return $next($context);
        }

        $now_ts = $context->clock->now()->getTimestamp();
        $is_fresh = $stale_item->isFresh($now_ts);

        if ($is_fresh) {
            $this->logger->debug('AsyncCache STRATEGY_HIT: Item is fresh, returning from cache', ['key' => $context->key]);
            $this->dispatchHit($context, $stale_item->data);

            /** @var T $data */
            $data = $stale_item->data;

            return resolve($data);
        }

        if (CacheStrategy::Background === $context->options->strategy) {
            $this->logger->debug('AsyncCache STRATEGY_BACKGROUND: Item is stale, returning stale and refreshing in background', ['key' => $context->key]);
            $this->dispatchStatus($context, CacheStatus::Stale);
            $this->dispatchHit($context, $stale_item->data);

            // Fire-and-forget background refresh
            $next($context)->catch(function (\Throwable $e) use ($context) {
                $this->logger->error('AsyncCache STRATEGY_BACKGROUND_ERROR: Background refresh failed', [
                    'key' => $context->key,
                    'error' => $e->getMessage(),
                ]);
            });

            /** @var T $data */
            $data = $stale_item->data;

            return resolve($data);
        }

        $this->logger->debug('AsyncCache STRATEGY_STRICT: Item is stale, waiting for fresh data', ['key' => $context->key]);

        return $next($context);
    }

    /**
     * @param CacheContext $context
     * @param mixed        $data
     */
    private function dispatchHit(CacheContext $context, mixed $data) : void
    {
        $now = (float) $context->clock->now()->format('U.u');
        $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Hit, $context->getElapsedTime(), $context->options->tags, $now));
        $this->dispatcher?->dispatch(new CacheHitEvent($context->key, $data, $now));
    }

    /**
     * @param CacheContext $context
     * @param CacheStatus  $status
     */
    private function dispatchStatus(CacheContext $context, CacheStatus $status) : void
    {
        $now = (float) $context->clock->now()->format('U.u');
        $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, $status, $context->getElapsedTime(), $context->options->tags, $now));
    }
}
