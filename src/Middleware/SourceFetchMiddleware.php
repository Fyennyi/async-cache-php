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
use Fyennyi\AsyncCache\Event\CacheMissEvent;
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * Final middleware in the chain that executes the actual data-fetching factory.
 */
class SourceFetchMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CacheStorage $storage,
        private LoggerInterface $logger,
        private ?EventDispatcherInterface $dispatcher = null
    ) {}

    /**
     * @template T
     * @inheritDoc
     *
     * @param  callable(CacheContext):PromiseInterface<T> $next
     * @return PromiseInterface<T>
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $this->logger->debug('SOURCE_FETCH: Fetching fresh data from source', ['key' => $context->key]);

        $start = (float) $context->clock->now()->format('U.u');

        try {
            /** @var PromiseInterface<T> $promise */
            $promise = $next($context);

            /** @var PromiseInterface<T> $result */
            $result = $promise->then(
                /**
                 * @param  T $data
                 * @return T
                 */
                function ($data) use ($context, $start) {
                    $now = (float) $context->clock->now()->format('U.u');
                    $generation_time = $now - $start;

                    $this->logger->debug('SOURCE_FETCH_SUCCESS: Successfully fetched from source', [
                        'key' => $context->key,
                        'generation_time' => round($generation_time, 4),
                    ]);

                    $this->dispatcher?->dispatch(new CacheStatusEvent(
                        $context->key,
                        CacheStatus::Miss,
                        $context->getElapsedTime(),
                        $context->options->tags,
                        $now
                    ));

                    // Background persistence - handle errors to avoid breaking the response
                    $this->storage->set($context->key, $data, $context->options, $generation_time)->catch(
                        function (\Throwable $e) use ($context) {
                            $this->logger->error('PERSISTENCE_ERROR: Failed to save fresh data to cache', [
                                'key' => $context->key,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    );

                    return $data;
                }
            )->catch(function (\Throwable $e) use ($context) {
                $this->logger->debug('SOURCE_FETCH_ERROR: Pipeline execution failed', [
                    'key' => $context->key,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            });

            return $result;
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
}
