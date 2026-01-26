<?php

namespace Fyennyi\AsyncCache\RateLimiter;

// dependencies removed for inline implementation

/**
 * Token bucket rate limiter implementation
 */
class TokenBucketRateLimiter implements RateLimiterInterface
{
    private array $tokens = [];
    private int $capacity;
    private int $refillRate;
    private int $refillInterval;

    public function __construct(int $capacity = 100, int $refillRate = 10, int $refillInterval = 1)
    {
        $this->capacity = $capacity;
        $this->refillRate = $refillRate;
        $this->refillInterval = $refillInterval;
    }

    public function isLimited(string $key): bool
    {
        return !$this->allow($key, 1);
    }

    public function recordExecution(string $key): void
    {
        // no-op for token bucket; consumption happens in allow()
    }

    public function configure(string $key, int $seconds): void
    {
        // Simple reconfiguration: set interval and derive a rough refill rate
        $this->refillInterval = max(1, $seconds);
        if ($seconds > 0) {
            $this->refillRate = (int) max(1, intdiv($this->capacity, $seconds));
        }
    }

    public function clear(?string $key = null): void
    {
        if ($key === null) {
            $this->tokens = [];
        } else {
            unset($this->tokens[$key]);
        }
    }

    private function now(): int
    {
        return time();
    }

    public function allow(string $key, int $limit = 1): bool
    {
        $now = $this->now();
        if (!isset($this->tokens[$key])) {
            $this->tokens[$key] = ['tokens' => $this->capacity, 'last_refill' => $now];
        }
        $bucket = &$this->tokens[$key];

        $timeElapsed = $now - $bucket['last_refill'];
        $tokensToAdd = ($timeElapsed / $this->refillInterval) * $this->refillRate;
        $bucket['tokens'] = min($this->capacity, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;

        if ($bucket['tokens'] >= $limit) {
            $bucket['tokens'] -= $limit;
            return true;
        }
        return false;
    }

    public function getAvailableTokens(string $key): int
    {
        if (!isset($this->tokens[$key])) {
            return $this->capacity;
        }
        $now = $this->now();
        $bucket = &$this->tokens[$key];
        $timeElapsed = $now - $bucket['last_refill'];
        $tokensToAdd = ($timeElapsed / $this->refillInterval) * $this->refillRate;
        $bucket['tokens'] = min($this->capacity, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;
        return (int) $bucket['tokens'];
    }
}
