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
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Promise\PromiseInterface;

/**
 * High-availability middleware that catches exceptions and serves stale data.
 */
class StaleOnErrorMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    /**
     * @param LoggerInterface|null          $logger     Logger for reporting failures
     * @param EventDispatcherInterface|null $dispatcher Dispatcher for telemetry events
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        private ?EventDispatcherInterface $dispatcher = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Catches errors and returns stale data if available.
     *
     * @template T
     *
     * @param  callable(CacheContext):PromiseInterface<T> $next Next handler in the chain
     * @return PromiseInterface<T>                        Promise resolving to fresh or stale data
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        /** @var PromiseInterface<T> $promise */
        $promise = $next($context);

        return $promise->catch(
            function (\Throwable $reason) use ($context) {
                $msg = $reason->getMessage();

                if (null !== $context->stale_item) {
                    $this->logger->warning('AsyncCache STALE_ON_ERROR: fetch failed, serving stale data', [
                        'key' => $context->key,
                        'reason' => $msg
                    ]);

                    $now = (float) $context->clock->now()->format('U.u');
                    $this->dispatcher?->dispatch(new CacheStatusEvent(
                        $context->key,
                        CacheStatus::Stale,
                        $now - $context->start_time,
                        $context->options->tags,
                        $now
                    ));

                    /** @var T $stale_data */
                    $stale_data = $context->stale_item->data;

                    return $stale_data;
                }

                throw $reason;
            }
        );
    }
}
