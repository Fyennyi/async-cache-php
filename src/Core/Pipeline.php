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

namespace Fyennyi\AsyncCache\Core;

use Fyennyi\AsyncCache\Middleware\MiddlewareInterface;
use React\Promise\PromiseInterface;

/**
 * Orchestrates the recursive execution of the middleware stack.
 */
class Pipeline
{
    /**
     * @param MiddlewareInterface[] $middlewares Stack of handlers to execute
     */
    public function __construct(
        private array $middlewares = []
    ) {}

    /**
     * Sends the context through the pipeline towards the final destination.
     *
     * @template T
     *
     * @param  CacheContext                               $context     The current state object
     * @param  callable(CacheContext):PromiseInterface<T> $destination The final handler (usually the fetcher)
     * @return PromiseInterface<T>                        Combined promise representing the full pipeline resolution
     */
    public function send(CacheContext $context, callable $destination) : PromiseInterface
    {
        /** @var \Closure(CacheContext):PromiseInterface<T> $pipeline */
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            function ($next, MiddlewareInterface $middleware) {
                return function (CacheContext $context) use ($next, $middleware) {
                    try {
                        /** @var \Closure(CacheContext):PromiseInterface<T> $next */
                        return $middleware->handle($context, $next);
                    } catch (\Throwable $e) {
                        return \React\Promise\reject($e);
                    }
                };
            },
            function (CacheContext $context) use ($destination) {
                try {
                    return $destination($context);
                } catch (\Throwable $e) {
                    return \React\Promise\reject($e);
                }
            }
        );

        return $pipeline($context);
    }
}
