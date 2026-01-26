<?php

namespace Fyennyi\AsyncCache\Middleware;

use Fyennyi\AsyncCache\Core\CacheContext;
use GuzzleHttp\Promise\PromiseInterface;

/**
 * Interface for all AsyncCache middlewares
 */
interface MiddlewareInterface
{
    /**
     * Handle the cache request
     * 
     * @param CacheContext $context The current resolution state
     * @param callable $next The next middleware in the pipeline
     * @return PromiseInterface
     */
    public function handle(CacheContext $context, callable $next): PromiseInterface;
}
