<?php

namespace Fyennyi\AsyncCache\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Interface for AsyncCache middleware
 */
interface MiddlewareInterface
{
    /**
     * Handles the cache wrapping process
     * 
     * @param string $key Cache key
     * @param callable $promise_factory Original data factory
     * @param CacheOptions $options Request options
     * @param callable $next The next middleware or the core logic
     * @return PromiseInterface
     */
    public function handle(string $key, callable $promise_factory, CacheOptions $options, callable $next): PromiseInterface;
}
