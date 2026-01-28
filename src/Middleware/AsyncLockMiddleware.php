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
use Fyennyi\AsyncCache\Event\CacheHitEvent;
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * Synchronization middleware that prevents race conditions using Symfony Lock.
 */
class AsyncLockMiddleware implements MiddlewareInterface
{
    /** @var array<string, LockInterface> Active locks storage */
    private array $active_locks = [];

    /**
     * @param LockFactory                   $lock_factory Symfony Lock Factory
     * @param CacheStorage                  $storage      The cache interaction layer
     * @param LoggerInterface               $logger       Logging implementation
     * @param EventDispatcherInterface|null $dispatcher   Event dispatcher for telemetry
     */
    public function __construct(
        private LockFactory $lock_factory,
        private CacheStorage $storage,
        private LoggerInterface $logger,
        private ?EventDispatcherInterface $dispatcher = null
    ) {}

    /**
     * Orchestrates non-blocking lock acquisition and cache population.
     *
     * @template T
     *
     * @param  callable(CacheContext):PromiseInterface<T> $next
     * @return PromiseInterface<T>
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $lock_key = 'lock:' . $context->key;
        $lock = $this->lock_factory->createLock($lock_key, 30.0);

        if ($lock->acquire(false)) {
            $this->logger->debug('AsyncCache LOCK_ACQUIRED: immediate', ['key' => $context->key]);
            $this->active_locks[$lock_key] = $lock;

            return $this->handleWithLock($context, $next, $lock_key);
        }

        if (null !== $context->stale_item) {
            $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Stale, microtime(true) - $context->start_time, $context->options->tags));
            $this->dispatcher?->dispatch(new CacheHitEvent($context->key, $context->stale_item->data));
            /** @var T $stale_data */
            $stale_data = $context->stale_item->data;

            return \React\Promise\resolve($stale_data);
        }

        $this->logger->debug('AsyncCache LOCK_BUSY: waiting for lock asynchronously', ['key' => $context->key]);

        $start_time = microtime(true);
        $timeout = 10.0;
        $deferred = new Deferred();

        $attempt = function () use (&$attempt, $context, $next, $lock_key, $start_time, $timeout, $deferred) {
            try {
                $lock = $this->lock_factory->createLock($lock_key, 30.0);

                if ($lock->acquire(false)) {
                    $this->logger->debug('AsyncCache LOCK_ACQUIRED: async', ['key' => $context->key]);
                    $this->active_locks[$lock_key] = $lock;

                    $this->storage->get($context->key, $context->options)->then(
                        function ($cached_item) use ($context, $next, $lock_key, $start_time, $deferred) {
                            if ($cached_item instanceof CachedItem && $cached_item->isFresh()) {
                                $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Hit, microtime(true) - $start_time, $context->options->tags));
                                $this->releaseLock($lock_key);
                                $deferred->resolve($cached_item->data);

                                return;
                            }

                            /** @var PromiseInterface<T> $inner_promise */
                            $inner_promise = $this->handleWithLock($context, $next, $lock_key);
                            $inner_promise->then(
                                fn ($v) => $deferred->resolve($v)
                            )->catch(function (\Throwable $e) use ($deferred) {
                                $this->logger->error('AsyncCache LOCK_INNER_ERROR: {msg}', ['msg' => $e->getMessage()]);
                                $deferred->reject($e);
                            });
                        }
                    )->catch(function (\Throwable $e) use ($context, $lock_key, $deferred) {
                        $this->logger->error('AsyncCache LOCK_STORAGE_ERROR: {msg}', ['key' => $context->key, 'msg' => $e->getMessage()]);
                        $this->releaseLock($lock_key);
                        $deferred->reject($e);
                    });

                    return;
                }

                if (microtime(true) - $start_time >= $timeout) {
                    $deferred->reject(new \RuntimeException("Could not acquire lock for key: {$context->key} (Timeout)"));

                    return;
                }

                // Retry after delay
                \React\Promise\Timer\resolve(0.05)->then(function () use ($attempt) {
                    $attempt();
                });
            } catch (\Throwable $e) {
                $this->logger->error('AsyncCache LOCK_RETRY_ERROR: {msg}', ['msg' => $e->getMessage()]);
                $deferred->reject($e);
            }
        };

        $attempt();

        /** @var PromiseInterface<T> $promise */
        $promise = $deferred->promise();
        $promise->catch(function (\Throwable $e) use ($context) {
            $this->logger->debug('AsyncCache LOCK_PIPELINE_ERROR: {msg}', ['key' => $context->key, 'msg' => $e->getMessage()]);
        });

        return $promise;
    }

    /**
     * Executes next middleware and ensures lock release.
     *
     * @template T
     *
     * @param  CacheContext                               $context  The resolution state
     * @param  callable(CacheContext):PromiseInterface<T> $next     Next handler in the chain
     * @param  string                                     $lock_key Key of the acquired lock
     * @return PromiseInterface<T>                        Result promise
     */
    private function handleWithLock(CacheContext $context, callable $next, string $lock_key) : PromiseInterface
    {
        try {
            /** @var PromiseInterface<T> $promise */
            $promise = $next($context);

            return $promise->finally(function () use ($lock_key) {
                $this->releaseLock($lock_key);
            });
        } catch (\Throwable $e) {
            $this->releaseLock($lock_key);

            return \React\Promise\reject($e);
        }
    }

    /**
     * Safely releases and removes the lock from tracking.
     *
     * @param string $lock_key Unique identifier of the lock to release
     */
    private function releaseLock(string $lock_key) : void
    {
        if (isset($this->active_locks[$lock_key])) {
            $this->logger->debug('AsyncCache LOCK_RELEASED', ['key' => $lock_key]);
            $this->active_locks[$lock_key]->release();
            unset($this->active_locks[$lock_key]);
        } else {
            $this->logger->warning('AsyncCache LOCK_RELEASE_FAILED: lock not found in active_locks', ['key' => $lock_key]);
        }
    }
}
