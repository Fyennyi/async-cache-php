<?php

namespace Fyennyi\AsyncCache;

use Fyennyi\AsyncCache\Exception\RateLimitException;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\RateLimiter\RateLimiterFactory;
use Fyennyi\AsyncCache\RateLimiter\RateLimiterInterface;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Fyennyi\AsyncCache\Lock\LockInterface;
use Fyennyi\AsyncCache\Lock\InMemoryLockAdapter;
use Fyennyi\AsyncCache\Middleware\MiddlewareInterface;
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
    
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * @param  CacheInterface  $cache_adapter  The PSR-16 cache implementation
     * @param  RateLimiterInterface|null  $rate_limiter  The rate limiter implementation
     * @param  string  $rate_limiter_type  Type of rate limiter to use
     * @param  LoggerInterface|null  $logger  The PSR-3 logger implementation
     * @param  LockInterface|null  $lock_provider  The distributed lock provider
     * @param  MiddlewareInterface[]  $middlewares  Optional middleware stack
     */
    public function __construct(
        private CacheInterface $cache_adapter,
        private ?RateLimiterInterface $rate_limiter = null,
        private string $rate_limiter_type = 'auto',
        private ?LoggerInterface $logger = null,
        private ?LockInterface $lock_provider = null,
        array $middlewares = []
    ) {
        $this->logger = $this->logger ?? new NullLogger();
        $this->storage = new CacheStorage($this->cache_adapter, $this->logger);
        $this->lock_provider = $this->lock_provider ?? new InMemoryLockAdapter();
        $this->middlewares = $middlewares;

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
     */
    public function wrap(string $key, callable $promise_factory, CacheOptions $options) : PromiseInterface
    {
        if (empty($this->middlewares)) {
            return $this->processWrap($key, $promise_factory, $options);
        }

        // Build middleware chain
        $pipeline = array_reverse($this->middlewares);
        $next = fn(string $k, callable $f, CacheOptions $o) => $this->processWrap($k, $f, $o);

        foreach ($pipeline as $middleware) {
            $currentNext = $next;
            $next = fn(string $k, callable $f, CacheOptions $o) => $middleware->handle($k, $f, $o, $currentNext);
        }

        return $next($key, $promise_factory, $options);
    }

    /**
     * Core logic of wrapping (formerly wrap)
     */
    private function processWrap(string $key, callable $promise_factory, CacheOptions $options) : PromiseInterface
    {
        // 1. Try to fetch from cache first
        $cached_item = null;
        if (! $options->force_refresh) {
            $cached_item = $this->storage->get($key, $options);
        }

        // 2. Check if cache is fresh (Hit)
        if ($cached_item instanceof CachedItem) {
            $is_fresh = $cached_item->isFresh();

            // Probabilistic Early Expiration (X-Fetch)
            if ($is_fresh && $options->x_fetch_beta > 0 && $cached_item->generationTime > 0) {
                $rand = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
                $check = time() - ($cached_item->generationTime * $options->x_fetch_beta * log($rand));
                
                if ($check > $cached_item->logicalExpireTime) {
                    $this->logger->info('AsyncCache X-FETCH: probabilistic early expiration triggered', [
                        'key' => $key,
                        'ttl_left' => $cached_item->logicalExpireTime - time()
                    ]);
                    $is_fresh = false;
                }
            }

            if ($is_fresh) {
                $this->logger->debug('AsyncCache HIT: fresh data returned', ['key' => $key]);
                return Create::promiseFor($cached_item->data);
            }

            // Cache is stale. Can we do background refresh?
            if ($options->background_refresh && ! $options->force_refresh) {
                $this->logger->info('AsyncCache STALE: triggering background refresh', ['key' => $key]);
                $this->fetch($key, $promise_factory, $options, $cached_item);
                return Create::promiseFor($cached_item->data);
            }
        }

        // 3. Cache is missed or stale
        return $this->fetch($key, $promise_factory, $options, $cached_item);
    }

    /**
     * Internal method to fetch fresh data, handle rate limiting, caching, coalescing and locking
     */
    private function fetch(string $key, callable $promise_factory, CacheOptions $options, ?CachedItem $stale_item = null) : PromiseInterface
    {
        if (isset($this->pending_promises[$key])) {
            $this->logger->info('AsyncCache COALESCE: reusing pending promise', ['key' => $key]);
            return $this->pending_promises[$key];
        }

        $lock_key = 'lock:' . $key;
        if (! $this->lock_provider->acquire($lock_key, 30.0, false)) {
            if ($stale_item !== null) {
                $this->logger->info('AsyncCache LOCK_BUSY: another process is fetching, serving stale data', ['key' => $key]);
                return Create::promiseFor($stale_item->data);
            }
            if (! $this->lock_provider->acquire($lock_key, 30.0, true)) {
                return Create::rejectionFor(new \RuntimeException("Could not acquire lock for key: $key"));
            }
        }

        $is_rate_limited = false;
        if ($options->rate_limit_key) {
            $is_rate_limited = $this->rate_limiter->isLimited($options->rate_limit_key);
        }

        if ($is_rate_limited) {
            $this->lock_provider->release($lock_key);
            if ($options->serve_stale_if_limited && $stale_item !== null) {
                $this->logger->warning('AsyncCache RATE_LIMIT: serving stale data', ['key' => $key]);
                return Create::promiseFor($stale_item->data);
            }
            return Create::rejectionFor(new RateLimitException($options->rate_limit_key));
        }

        $this->logger->info('AsyncCache MISS/STALE: fetching fresh data', ['key' => $key]);
        if ($options->rate_limit_key) {
            $this->rate_limiter->recordExecution($options->rate_limit_key);
        }

        $start_time = microtime(true);
        $promise = $promise_factory()->then(
            function ($data) use ($key, $options, $lock_key, $start_time) {
                $generation_time = microtime(true) - $start_time;
                unset($this->pending_promises[$key]);
                $this->lock_provider->release($lock_key);
                $this->storage->set($key, $data, $options, $generation_time);
                return $data;
            },
            function ($reason) use ($key, $lock_key) {
                unset($this->pending_promises[$key]);
                $this->lock_provider->release($lock_key);
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
     * Proxy methods
     */
    public function clear() : bool { return $this->storage->clear(); }
    public function delete(string $key) : bool { return $this->storage->delete($key); }
    public function getRateLimiter() : RateLimiterInterface { return $this->rate_limiter; }
    public function clearRateLimiter(?string $key = null) : void { 
        if (method_exists($this->rate_limiter, 'clear')) { $this->rate_limiter->clear($key); }
    }
}