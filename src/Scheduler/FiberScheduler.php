<?php

namespace Fyennyi\AsyncCache\Scheduler;

use React\Promise\PromiseInterface;
use function React\Async\delay;
use function React\Promise\resolve;

/**
 * Scheduler driver for PHP 8.1+ Fibers (via react/async)
 */
class FiberScheduler implements SchedulerInterface
{
    public function delay(float $seconds): PromiseInterface
    {
        // This will suspend the current Fiber without blocking the loop
        delay($seconds);
        return resolve(null);
    }

    public function resolve(mixed $value): PromiseInterface
    {
        return resolve($value);
    }

    public static function isSupported(): bool
    {
        // Fibers require PHP 8.1+ and react/async for orchestration
        return PHP_VERSION_ID >= 80100 && function_exists('React\Async\delay');
    }
}
