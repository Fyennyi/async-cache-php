<?php

namespace Fyennyi\AsyncCache\Lock;

use Fyennyi\AsyncCache\Bridge\GuzzlePromiseAdapter;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use React\Promise\Timer;

/**
 * Helper to turn synchronous locks into non-blocking async waits using react/promise-timer
 */
class AsyncLockWaiter
{
    /**
     * @param LockInterface $lock_provider
     * @param string $key
     * @param float $ttl
     * @param float $timeout Maximum time to wait in seconds
     * @param float $interval Polling interval in seconds (default 50ms)
     * @return PromiseInterface Resolves to bool
     */
    public static function waitFor(
        LockInterface $lock_provider,
        string $key,
        float $ttl = 30.0,
        float $timeout = 10.0,
        float $interval = 0.05
    ): PromiseInterface {
        $start_time = microtime(true);

        $attempt = function () use (&$attempt, $lock_provider, $key, $ttl, $timeout, $interval, $start_time) {
            // 1. Try to acquire without blocking
            if ($lock_provider->acquire($key, $ttl, false)) {
                return Create::promiseFor(true);
            }

            // 2. Check for timeout
            if (microtime(true) - $start_time >= $timeout) {
                return Create::promiseFor(false);
            }

            // 3. Non-blocking delay using react/promise-timer
            // This returns a promise that resolves after the interval, yielding control back to EventLoop
            return GuzzlePromiseAdapter::wrap(Timer\resolve($interval))->then($attempt);
        };

        return GuzzlePromiseAdapter::wrap($attempt());
    }
}