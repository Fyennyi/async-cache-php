<?php

namespace Tests\Unit;

use Fyennyi\AsyncCache\AsyncCacheManager;
use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use Fyennyi\AsyncCache\Model\CachedItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\LimiterInterface;

class AsyncCacheManagerTest extends TestCase
{
    private MockObject|CacheInterface $cache;
    private MockObject|LimiterInterface $rateLimiter;
    private LockFactory $lockFactory;
    private AsyncCacheManager $manager;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->rateLimiter = $this->createMock(LimiterInterface::class);
        $this->lockFactory = new LockFactory(new InMemoryStore()); // Use real in-memory locks

        $this->manager = new AsyncCacheManager(
            cache_adapter: $this->cache,
            rate_limiter: $this->rateLimiter,
            logger: new NullLogger(),
            lock_factory: $this->lockFactory
        );
    }

    public function testReturnsFreshCacheImmediately(): void
    {
        $key = 'test_key';
        $data = 'cached_data';
        $options = new CacheOptions(ttl: 60);

        // Mock cache hit via PSR adapter (CacheStorage calls adapter->get)
        // Note: CacheStorage wraps result in CachedItem.
        // Since we mock PSR cache, PsrToAsyncAdapter returns whatever PSR returns.
        // CacheStorage expects either CachedItem object or array ['d' => ..., 'e' => ...].

        $cachedItem = new CachedItem($data, time() + 100);
        // We simulate that the underlying cache returns a serialized version or object depending on adapter.
        // PsrToAsyncAdapter just passes value through.

        $this->cache->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($cachedItem);

        $future = $this->manager->wrap($key, fn () => 'new_data', $options);
        $result = $future->wait();

        $this->assertSame($data, $result);
    }

    public function testFetchesNewDataOnCacheMiss(): void
    {
        $key = 'test_key';
        $newData = 'new_data';
        $options = new CacheOptions(ttl: 60);

        // 1. Cache lookup
        $this->cache->expects($this->once())->method('get')->with($key)->willReturn(null);

        // 2. Cache set (after fetch)
        $this->cache->expects($this->once())->method('set');

        $future = $this->manager->wrap($key, fn () => $newData, $options);
        $result = $future->wait();

        $this->assertSame($newData, $result);
    }

    public function testForceRefreshStrategy(): void
    {
        $key = 'test_key';
        $newData = 'fresh_data';
        $options = new CacheOptions(ttl: 60, strategy: CacheStrategy::ForceRefresh);

        // Should NOT check cache for get (strategy bypasses lookup)
        // But might check for lock/etc. Actually CacheLookupMiddleware handles ForceRefresh by calling next() immediately.
        // So adapter->get should NOT be called by CacheLookupMiddleware.

        // Wait, CacheLookupMiddleware does: if (ForceRefresh) return next($context).
        // So get() is skipped.

        // Then SourceFetchMiddleware calls factory and sets cache.
        $this->cache->expects($this->once())->method('set');

        // Ensure get is never called (except maybe by other middlewares? No)
        $this->cache->expects($this->never())->method('get');

        $future = $this->manager->wrap($key, fn () => $newData, $options);
        $result = $future->wait();

        $this->assertSame($newData, $result);
    }

    public function testClearsCache(): void
    {
        $this->cache->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $future = $this->manager->clear();
        $result = $future->wait();

        // The adapter result (true) is returned
        $this->assertTrue($result);
    }

    public function testDeletesCacheKey(): void
    {
        $key = 'test_key';

        $this->cache->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willReturn(true);

        $future = $this->manager->delete($key);
        $result = $future->wait();

        $this->assertTrue($result);
    }
}
