<?php

namespace Fyennyi\AsyncCache\Scheduler;

use React\Promise\PromiseInterface;

/**
 * Interface to abstract async execution environments
 */
interface SchedulerInterface
{
    /**
     * Returns a promise that resolves after the specified delay
     */
    public function delay(float $seconds): PromiseInterface;

    /**
     * Resolves a value into a promise native to this runtime
     */
    public function resolve(mixed $value): PromiseInterface;

    /**
     * Detects if this runtime is supported by the current environment
     */
    public static function isSupported(): bool;
}
