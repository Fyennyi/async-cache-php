<?php

namespace Fyennyi\AsyncCache\Core;

use React\Promise\Timer\resolve as reactDelay;

/**
 * High-level timer for non-blocking delays
 */
class Timer
{
    /**
     * Returns a Future that resolves after the specified delay using ReactPHP
     */
    public static function delay(float $seconds): Future
    {
        $deferred = new Deferred(function() {
            // Auto-drive the loop if wait() is called
            if (class_exists('React\EventLoop\Loop')) {
                \React\EventLoop\Loop::run();
            }
        });

        if (class_exists('React\Promise\Timer\resolve')) {
            reactDelay($seconds)->then(fn() => $deferred->resolve(null));
        } else {
            // Fallback for non-async environments (safety only)
            usleep((int)($seconds * 1000000));
            $deferred->resolve(null);
        }

        return $deferred->future();
    }
}
