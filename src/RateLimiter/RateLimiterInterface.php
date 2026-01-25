<?php

namespace Fyennyi\AsyncCache\RateLimiter;

interface RateLimiterInterface
{
    /**
     * Checks if the action is currently rate limited
     *
     * @param  string  $key  The identifier for the limit (e.g., 'nominatim_api')
     * @return bool True if the action is blocked, False if allowed
     */
    public function isLimited(string $key) : bool;

    /**
     * Records a successful execution to update rate limit counters/timers
     *
     * @param  string  $key  The identifier for limit
     * @return void
     */
    public function recordExecution(string $key) : void;

    /**
     * Configures rate limit for a specific key
     *
     * @param  string  $key  The identifier for limit
     * @param  int  $seconds  The interval in seconds
     * @return void
     */
    public function configure(string $key, int $seconds) : void;

    /**
     * Clears rate limiter state
     *
     * @param  string|null  $key  The key to clear, or null to clear all
     * @return void
     */
    public function clear(?string $key = null) : void;
}
