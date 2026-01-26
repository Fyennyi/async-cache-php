<?php

namespace Fyennyi\AsyncCache\Middleware;

use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Core\Future;

/**
 * Implementation of the Singleflight (Request Coalescing) pattern.
 * Ensures that for multiple concurrent requests for the same key,
 * only one execution happens, and the result is shared.
 */
class CoalesceMiddleware implements MiddlewareInterface
{
    /** @var array<string, Future> */
    private static array $inFlight = [];

    public function handle(CacheContext $context, callable $next): Future
    {
        $key = $context->key;

        // If a request for this key is already in flight, return the existing Future
        if (isset(self::$inFlight[$key])) {
            return self::$inFlight[$key];
        }

        // Otherwise, start the execution and track it
        $future = $next($context);
        self::$inFlight[$key] = $future;

        // Clean up when the future is settled (resolved or rejected)
        $future->then(
            function ($value) use ($key) {
                unset(self::$inFlight[$key]);
                return $value;
            },
            function ($reason) use ($key) {
                unset(self::$inFlight[$key]);
                throw $reason;
            }
        );

        return $future;
    }
}
