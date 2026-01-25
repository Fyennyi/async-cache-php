<?php

namespace Fyennyi\AsyncCache;

use Fyennyi\AsyncCache\Enum\CacheStrategy;

/**
 * Builder for CacheOptions DTO
 */
class CacheOptionsBuilder
{
    private ?int $ttl = 3600;
    private int $stale_grace_period = 86400;
    private bool $serve_stale_if_limited = true;
    private CacheStrategy $strategy = CacheStrategy::Strict;
    private bool $compression = false;
    private int $compression_threshold = 1024;
    private bool $fail_safe = true;
    private float $x_fetch_beta = 1.0;
    private ?string $rate_limit_key = null;
    private array $tags = [];

    public static function create(): self
    {
        return new self();
    }

    public function withTtl(?int $ttl): self
    {
        $this->ttl = $ttl;
        return $this;
    }

    public function withStaleGracePeriod(int $seconds): self
    {
        $this->stale_grace_period = $seconds;
        return $this;
    }

    public function withStrategy(CacheStrategy $strategy): self
    {
        $this->strategy = $strategy;
        return $this;
    }

    public function withBackgroundRefresh(): self
    {
        $this->strategy = CacheStrategy::Background;
        return $this;
    }

    public function withForceRefresh(): self
    {
        $this->strategy = CacheStrategy::ForceRefresh;
        return $this;
    }

    public function withCompression(bool $enabled = true, int $threshold = 1024): self
    {
        $this->compression = $enabled;
        $this->compression_threshold = $threshold;
        return $this;
    }

    public function withFailSafe(bool $enabled = true): self
    {
        $this->fail_safe = $enabled;
        return $this;
    }

    public function withXFetch(float $beta = 1.0): self
    {
        $this->x_fetch_beta = $beta;
        return $this;
    }

    public function withRateLimit(string $key, bool $serveStale = true): self
    {
        $this->rate_limit_key = $key;
        $this->serve_stale_if_limited = $serveStale;
        return $this;
    }

    public function withTags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function build(): CacheOptions
    {
        return new CacheOptions(
            ttl: $this->ttl,
            stale_grace_period: $this->stale_grace_period,
            serve_stale_if_limited: $this->serve_stale_if_limited,
            strategy: $this->strategy,
            compression: $this->compression,
            compression_threshold: $this->compression_threshold,
            fail_safe: $this->fail_safe,
            x_fetch_beta: $this->x_fetch_beta,
            rate_limit_key: $this->rate_limit_key,
            tags: $this->tags
        );
    }
}
