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
use React\Promise\PromiseInterface;

/**
 * Middleware that prevents duplicate concurrent requests for the same key
 */
class CoalesceMiddleware implements MiddlewareInterface
{
    /** @var array<string, PromiseInterface> */
    private array $pending = [];

    /**
     * @inheritDoc
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        if (isset($this->pending[$context->key])) {
            return $this->pending[$context->key];
        }

        $promise = $next($context);
        $this->pending[$context->key] = $promise;

        $promise->finally(function () use ($context) {
            unset($this->pending[$context->key]);
        });

        return $promise;
    }
}
