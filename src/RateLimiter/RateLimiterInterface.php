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
     * Records a successful execution to update the rate limit counters/timers
     *
     * @param  string  $key  The identifier for the limit
     * @return void
     */
    public function recordExecution(string $key) : void;
}
