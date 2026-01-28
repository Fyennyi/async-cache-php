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

        try {
            $promise = $next($context)->then(
                function ($data) use ($context, $start) {
                    $generation_time = microtime(true) - $start;
                    $this->dispatcher?->dispatch(new CacheStatusEvent(
                        $context->key,
                        CacheStatus::Miss,
                        microtime(true) - $context->start_time,
                        $context->options->tags
                    ));

                    // Background persistence - handle errors to avoid breaking the response
                    $this->storage->set($context->key, $data, $context->options, $generation_time)->catch(function(\Throwable $e) use ($context) {
                        $this->logger->error('AsyncCache PERSISTENCE_ERROR: {msg}', ['key' => $context->key, 'msg' => $e->getMessage()]);
                    });

                    return $data;
                },
                function (\Throwable $e) {
                    throw $e;
                }
            );

            // Catch for the branch created by then() to avoid unhandled rejection logging
            $promise->catch(function(\Throwable $e) use ($context) {
                $this->logger->debug('AsyncCache FETCH_PIPELINE_ERROR: {msg}', ['key' => $context->key, 'msg' => $e->getMessage()]);
            });

            return $promise;
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
}
