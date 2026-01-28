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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\Promise\PromiseInterface;

/**
 * Middleware that prevents duplicate concurrent requests for the same key.
 */
class CoalesceMiddleware implements MiddlewareInterface
{
    /** @var array<string, PromiseInterface<mixed>> */
    private array $pending = [];

    /**
     * @param LoggerInterface|null $logger Logging implementation
     */
    public function __construct(
        private ?LoggerInterface $logger = null
    ) {
        $this->logger = $this->logger ?? new NullLogger();
    }

    /**
     * @template T
     *
     * @param  callable(CacheContext):PromiseInterface<T> $next
     * @return PromiseInterface<T>
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        if (isset($this->pending[$context->key])) {
            /** @var PromiseInterface<T> $pending_promise */
            $pending_promise = $this->pending[$context->key];
            $this->logger?->debug('AsyncCache COALESCE_HIT: returning existing promise', ['key' => $context->key]);

            return $pending_promise;
        }

        /** @var PromiseInterface<T> $promise */
        $promise = $next($context);

        $this->pending[$context->key] = $promise;

        $promise->finally(function () use ($context) {
            unset($this->pending[$context->key]);
        })->catch(function (\Throwable $e) use ($context) {
            $this->logger?->debug('AsyncCache COALESCE_ERROR: pending request failed', [
                'key' => $context->key,
                'msg' => $e->getMessage()
            ]);
        });

        return $promise;
    }
}
