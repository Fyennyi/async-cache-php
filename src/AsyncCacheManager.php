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
use Fyennyi\AsyncCache\Scheduler\SchedulerFactory;
use Fyennyi\AsyncCache\Scheduler\SchedulerInterface;
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
    private SchedulerInterface $scheduler;

    public function __construct(
        private CacheInterface $cache_adapter,
        private ?RateLimiterInterface $rate_limiter = null,
        private RateLimiterType $rate_limiter_type = RateLimiterType::Auto,
        private ?LoggerInterface $logger = null,
        private ?LockInterface $lock_provider = null,
        array $middlewares = [],
        private ?EventDispatcherInterface $dispatcher = null,
        ?SerializerInterface $serializer = null,
        ?SchedulerInterface $scheduler = null
    ) {
        $this->logger = $this->logger ?? new NullLogger();
        $this->storage = new CacheStorage($this->cache_adapter, $this->logger, $serializer);
        $this->lock_provider = $this->lock_provider ?? new InMemoryLockAdapter();
        $this->scheduler = $scheduler ?? SchedulerFactory::create();

        if ($this->rate_limiter === null) {
            $this->rate_limiter = RateLimiterFactory::create($this->rate_limiter_type, $this->cache_adapter);
        }

        if (empty($middlewares)) {
            $middlewares = [
                new CacheLookupMiddleware($this->storage, $this->logger, $this->dispatcher),
                new AsyncLockMiddleware($this->lock_provider, $this->storage, $this->scheduler, $this->logger, $this->dispatcher),
                new SourceFetchMiddleware($this->storage, $this->logger, $this->dispatcher)
            ];
        }

        $this->pipeline = new Pipeline($middlewares);
    }

    public function wrap(string $key, callable $promise_factory, CacheOptions $options) : \GuzzleHttp\Promise\PromiseInterface
    {
        $context = new CacheContext($key, $promise_factory, $options);
        $reactPromise = $this->pipeline->send($context, function (CacheContext $ctx) {
            return PromiseBridge::toReact(($ctx->promiseFactory)());
        });
        return PromiseBridge::toGuzzle($reactPromise);
    }

    public function get(string $key, callable $promise_factory, CacheOptions $options): mixed
    {
        $promise = $this->wrap($key, $promise_factory, $options);

        if (function_exists('React\Async\await')) {
            // Check if we are inside a Fiber
            if (\Fiber::getCurrent() === null && class_exists('\React\EventLoop\Loop') && \React\EventLoop\Loop::get() !== null) {
                $this->logger->warning("AsyncCache: get() called outside of a Fiber in an async environment. This will block the Event Loop!");
            }
            return \React\Async\await(Bridge\PromiseBridge::toReact($promise));
        }

        return $promise->wait();
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