<?php

namespace Fyennyi\AsyncCache;

use Fyennyi\AsyncCache\Exception\RateLimitException;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\RateLimiter\RateLimiterFactory;
use Fyennyi\AsyncCache\RateLimiter\RateLimiterInterface;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class AsyncCacheManager
{
    /** @var array<string, PromiseInterface> */
    private array $pending_promises = [];

    private CacheStorage $storage;

    /**
     * @param  CacheInterface  $cache_adapter  The PSR-16 cache implementation
     * @param  RateLimiterInterface|null  $rate_limiter  The rate limiter implementation
     * @param  string  $rate_limiter_type  Type of rate limiter to use
     * @param  LoggerInterface|null  $logger  The PSR-3 logger implementation
     */
    public function __construct(
        private CacheInterface $cache_adapter,
        private ?RateLimiterInterface $rate_limiter = null,
        private string $rate_limiter_type = 'auto',
        private ?LoggerInterface $logger = null
    ) {
        $this->logger = $this->logger ?? new NullLogger();
        $this->storage = new CacheStorage($this->cache_adapter, $this->logger);

        if ($this->rate_limiter === null) {
            $this->rate_limiter = match ($this->rate_limiter_type) {
                'symfony' => RateLimiterFactory::create('symfony', $this->cache_adapter),
                'in_memory' => RateLimiterFactory::create('in_memory', $this->cache_adapter),
                'auto' => RateLimiterFactory::createBest($this->cache_adapter),
                default => throw new \InvalidArgumentException("Unknown rate limiter type: {$this->rate_limiter_type}")
            };
        }
    }

    /**
     * Wraps an asynchronous operation with caching, rate limiting, and stale-data fallback
     *
     * @param  string  $key  Unique cache key for the data
     * @param  callable(): PromiseInterface  $promise_factory  Function that returns the Promise to execute
     * @param  CacheOptions  $options  Configuration for this request
     * @return PromiseInterface
     */
    public function wrap(string $key, callable $promise_factory, CacheOptions $options) : PromiseInterface
    {
        // 1. Try to fetch from cache first
        $cached_item = null;
        if (! $options->force_refresh) {
            $cached_item = $this->storage->get($key, $options);
        }

        // 2. Check if cache is fresh (Hit)
        if ($cached_item instanceof CachedItem) {
            if ($cached_item->isFresh()) {
                $this->logger->debug('AsyncCache HIT: fresh data returned', ['key' => $key]);
                return Create::promiseFor($cached_item->data);
            }

            // Cache is stale. Can we do background refresh?
            if ($options->background_refresh && ! $options->force_refresh) {
                $this->logger->info('AsyncCache STALE: triggering background refresh', ['key' => $key]);
                $this->fetch($key, $promise_factory, $options);
                return Create::promiseFor($cached_item->data);
            }
        }

        // 3. Cache is missed or stale (No background refresh or not available).
        
        // --- Promise Coalescing Start ---
        if (isset($this->pending_promises[$key])) {
            $this->logger->info('AsyncCache COALESCE: reusing pending promise', ['key' => $key]);
            return $this->pending_promises[$key];
        }
        // --- Promise Coalescing End ---

        $is_rate_limited = false;
        if ($options->rate_limit_key) {
            $is_rate_limited = $this->rate_limiter->isLimited($options->rate_limit_key);
        }

        // 4. Stale Fallback Strategy
        if ($is_rate_limited) {
            if ($options->serve_stale_if_limited && $cached_item instanceof CachedItem) {
                $this->logger->warning('AsyncCache RATE_LIMIT: serving stale data', [
                    'key' => $key,
                    'rate_limit_key' => $options->rate_limit_key
                ]);
                return Create::promiseFor($cached_item->data);
            }

            $this->logger->error('AsyncCache RATE_LIMIT: execution blocked', [
                'key' => $key,
                'rate_limit_key' => $options->rate_limit_key
            ]);
            return Create::rejectionFor(new RateLimitException($options->rate_limit_key));
        }

        // 5. Execute actual request
        $this->logger->info('AsyncCache MISS: fetching fresh data', ['key' => $key]);
        return $this->fetch($key, $promise_factory, $options);
    }

    /**
     * Internal method to fetch fresh data, handle rate limiting, caching and coalescing
     */
    private function fetch(string $key, callable $promise_factory, CacheOptions $options) : PromiseInterface
    {
        if (isset($this->pending_promises[$key])) {
            return $this->pending_promises[$key];
        }

        if ($options->rate_limit_key) {
            $this->rate_limiter->recordExecution($options->rate_limit_key);
        }

        $promise = $promise_factory()->then(
            function ($data) use ($key, $options) {
                unset($this->pending_promises[$key]);
                $this->storage->set($key, $data, $options);
                return $data;
            },
            function ($reason) use ($key) {
                unset($this->pending_promises[$key]);
                $this->logger->error('AsyncCache FETCH_ERROR: failed to fetch fresh data', [
                    'key' => $key,
                    'reason' => $reason
                ]);
                throw $reason;
            }
        );

        return $this->pending_promises[$key] = $promise;
    }

    /**
     * Wipes the entire cache's keys
     */
    public function clear() : bool
    {
        return $this->storage->clear();
    }

    /**
     * Delete an item from the cache by its unique key
     */
    public function delete(string $key) : bool
    {
        return $this->storage->delete($key);
    }

    /**
     * Returns the rate limiter instance
     */
    public function getRateLimiter() : RateLimiterInterface
    {
        return $this->rate_limiter;
    }

    /**
     * Clears the rate limiter state
     */
    public function clearRateLimiter(?string $key = null) : void
    {
        if (method_exists($this->rate_limiter, 'clear')) {
            $this->rate_limiter->clear($key);
        }
    }
}
