<?php

namespace Fyennyi\AsyncCache;

use Fyennyi\AsyncCache\Bridge\PromiseBridge;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Core\Pipeline;
use Fyennyi\AsyncCache\Enum\RateLimiterType;
use Fyennyi\AsyncCache\Lock\InMemoryLockAdapter;
use Fyennyi\AsyncCache\Lock\LockInterface;
use Fyennyi\AsyncCache\Middleware\AsyncLockMiddleware;
use Fyennyi\AsyncCache\Middleware\CacheLookupMiddleware;
use Fyennyi\AsyncCache\Middleware\SourceFetchMiddleware;
use Fyennyi\AsyncCache\RateLimiter\RateLimiterFactory;
use Fyennyi\AsyncCache\RateLimiter\RateLimiterInterface;
use Fyennyi\AsyncCache\Runtime\RuntimeFactory;
use Fyennyi\AsyncCache\Runtime\RuntimeInterface;
use Fyennyi\AsyncCache\Serializer\SerializerInterface;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class AsyncCacheManager
{
    private Pipeline $pipeline;
    private CacheStorage $storage;
    private RuntimeInterface $runtime;

    public function __construct(
        private CacheInterface $cache_adapter,
        private ?RateLimiterInterface $rate_limiter = null,
        private RateLimiterType $rate_limiter_type = RateLimiterType::Auto,
        private ?LoggerInterface $logger = null,
        private ?LockInterface $lock_provider = null,
        array $middlewares = [],
        private ?EventDispatcherInterface $dispatcher = null,
        ?SerializerInterface $serializer = null,
        ?RuntimeInterface $runtime = null
    ) {
        $this->logger = $this->logger ?? new NullLogger();
        $this->storage = new CacheStorage($this->cache_adapter, $this->logger, $serializer);
        $this->lock_provider = $this->lock_provider ?? new InMemoryLockAdapter();
        $this->runtime = $runtime ?? RuntimeFactory::create();

        if ($this->rate_limiter === null) {
            $this->rate_limiter = RateLimiterFactory::create($this->rate_limiter_type, $this->cache_adapter);
        }

        // Build default pipeline if empty
        if (empty($middlewares)) {
            $middlewares = [
                new CacheLookupMiddleware($this->storage, $this->logger, $this->dispatcher),
                new AsyncLockMiddleware($this->lock_provider, $this->storage, $this->runtime, $this->logger, $this->dispatcher),
                new SourceFetchMiddleware($this->storage, $this->logger, $this->dispatcher)
            ];
        }

        $this->pipeline = new Pipeline($middlewares);
    }

    /**
     * Wraps an asynchronous operation with caching, rate limiting, and stale-data fallback
     * 
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function wrap(string $key, callable $promise_factory, CacheOptions $options) : \GuzzleHttp\Promise\PromiseInterface
    {
        $context = new CacheContext($key, $promise_factory, $options);
        
        $reactPromise = $this->pipeline->send($context, function (CacheContext $ctx) {
            return PromiseBridge::toReact(($ctx->promiseFactory)());
        });

        return PromiseBridge::toGuzzle($reactPromise);
    }

    public function increment(string $key, int $step = 1, ?CacheOptions $options = null): \GuzzleHttp\Promise\PromiseInterface
    {
        $options = $options ?? new CacheOptions();
        $lockKey = 'lock:counter:' . $key;
        if ($this->lock_provider->acquire($lockKey, 10.0, true)) {
            $item = $this->storage->get($key, $options);
            $currentValue = $item ? (int) $item->data : 0;
            $newValue = $currentValue + $step;
            $this->storage->set($key, $newValue, $options);
            $this->lock_provider->release($lockKey);
            return \GuzzleHttp\Promise\Create::promiseFor($newValue);
        }
        return \GuzzleHttp\Promise\Create::rejectionFor(new \RuntimeException("Could not acquire lock for incrementing key: $key"));
    }

    public function decrement(string $key, int $step = 1, ?CacheOptions $options = null): \GuzzleHttp\Promise\PromiseInterface
    {
        return $this->increment($key, -$step, $options);
    }

    public function invalidateTags(array $tags) : void { $this->storage->invalidateTags($tags); }
    public function clear() : bool { return $this->cache_adapter->clear(); }
    public function delete(string $key) : bool { return $this->cache_adapter->delete($key); }
    public function getRateLimiter() : RateLimiterInterface { return $this->rate_limiter; }
    public function clearRateLimiter(?string $key = null) : void { $this->rate_limiter->clear($key); }
}
