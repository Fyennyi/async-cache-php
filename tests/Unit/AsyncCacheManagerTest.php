<?php

namespace Fyennyi\AsyncCache; // shadow namespace for microtime override

function microtime($as_float = false)
{
    if ($as_float && !empty($GLOBALS['mock_microtime_timeout'])) {
        $val = $GLOBALS['mock_microtime_value'] ?? 2000000000.0;
        $GLOBALS['mock_microtime_value'] = $val + 10.0; // advance time by 10s per call (instantly trigger timeout)
        return $val;
    }
    return \microtime($as_float);
}

namespace Tests\Unit;

use Fyennyi\AsyncCache\AsyncCacheManager;
use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\AsyncCacheAdapterInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\LimiterInterface;
use function React\Async\await;

class AsyncCacheManagerTest extends TestCase
{
    protected function tearDown() : void
    {
        $GLOBALS['mock_microtime_timeout'] = false;
        unset($GLOBALS['mock_microtime_value']);
    }
    private MockObject|CacheInterface $cache;
    private MockObject|LimiterInterface $rateLimiter;
    private LockFactory $lockFactory;
    private AsyncCacheManager $manager;

    protected function setUp() : void
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

    public function testReturnsFreshCacheImmediately() : void
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

        $promise = $this->manager->wrap($key, fn () => 'new_data', $options);
        $result = await($promise);

        $this->assertSame($data, $result);
    }

    public function testFetchesNewDataOnCacheMiss() : void
    {
        $key = 'test_key';
        $newData = 'new_data';
        $options = new CacheOptions(ttl: 60);

        // 1. Cache lookup
        $this->cache->expects($this->once())->method('get')->with($key)->willReturn(null);

        $promise = $this->manager->wrap($key, fn () => $newData, $options);
        $result = await($promise);

        $this->assertSame($newData, $result);
    }

    public function testForceRefreshStrategy() : void
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

        $promise = $this->manager->wrap($key, fn () => $newData, $options);
        $result = await($promise);

        $this->assertSame($newData, $result);
    }

    public function testClearsCache() : void
    {
        $this->cache->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $promise = $this->manager->clear();
        $result = await($promise);

        // The adapter result (true) is returned
        $this->assertTrue($result);
    }

    public function testDeletesCacheKey() : void
    {
        $key = 'test_key';

        $this->cache->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willReturn(true);

        $promise = $this->manager->delete($key);
        $result = await($promise);

        $this->assertTrue($result);
    }

    public function testIncrementTimeout() : void
    {
        // Create isolated mocks to simulate timeout behavior for increment()
        $adapter = $this->createMock(AsyncCacheAdapterInterface::class);
        $lock = $this->createMock('\Symfony\Component\Lock\SharedLockInterface');
        $lockFactory = $this->createMock(LockFactory::class);

        $lockFactory->method('createLock')->willReturn($lock);
        // First call to acquire(false) simulates timeout
        $lock->method('acquire')->willReturn(false);

        // Enable sped-up microtime for this timeout test
        $GLOBALS['mock_microtime_timeout'] = true;
        $mgr = new AsyncCacheManager($adapter, lock_factory: $lockFactory);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not acquire lock for incrementing key');

        await($mgr->increment('k'));
    }
    public function testIncrementAcquiresLockAndUpdatesValue() : void
    {
        $key = 'counter';
        $cache = $this->createMock(CacheInterface::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock('\Symfony\Component\Lock\SharedLockInterface');
        $lockFactory->method('createLock')->with('lock:counter:' . $key)->willReturn($lock);
        $lock->method('acquire')->willReturn(true);

        $cache->expects($this->once())->method('get')->with($key)->willReturn(new CachedItem(10, time() + 3600));
        $cache->expects($this->once())->method('set')->with($key, $this->callback(function ($item) {
            return $item instanceof CachedItem && 11 === $item->data;
        }))->willReturn(true);

        $mgr = new AsyncCacheManager(cache_adapter: $cache, rate_limiter: null, logger: new NullLogger(), lock_factory: $lockFactory);
        $result = await($mgr->increment($key, 1));
        $this->assertSame(11, $result);
    }

    public function testIncrementInitializesValueIfMissing() : void
    {
        $key = 'counter';
        $cache = $this->createMock(CacheInterface::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock('\Symfony\Component\Lock\SharedLockInterface');
        $lockFactory->method('createLock')->with('lock:counter:' . $key)->willReturn($lock);
        $lock->method('acquire')->willReturn(true);

        $cache->method('get')->with($key)->willReturn(null);
        $cache->expects($this->once())->method('set')->with($key, $this->callback(function ($item) {
            return $item instanceof CachedItem && 1 === $item->data;
        }))->willReturn(true);

        $mgr = new AsyncCacheManager(cache_adapter: $cache, rate_limiter: null, logger: new NullLogger(), lock_factory: $lockFactory);
        $result = await($mgr->increment($key));
        $this->assertSame(1, $result);
    }

    public function testDecrement() : void
    {
        $key = 'counter';
        $cache = $this->createMock(CacheInterface::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock('\Symfony\Component\Lock\SharedLockInterface');
        $lockFactory->method('createLock')->with('lock:counter:' . $key)->willReturn($lock);
        $lock->method('acquire')->willReturn(true);

        $cache->method('get')->with($key)->willReturn(new CachedItem(10, time() + 3600));
        $cache->expects($this->once())->method('set')->with($key, $this->callback(function ($item) {
            return $item instanceof CachedItem && 5 === $item->data;
        }))->willReturn(true);

        $mgr = new AsyncCacheManager(cache_adapter: $cache, rate_limiter: null, logger: new NullLogger(), lock_factory: $lockFactory);
        $result = await($mgr->decrement($key, 5));
        $this->assertSame(5, $result);
    }

    public function testInvalidateTags() : void
    {
        $cache = $this->createMock(CacheInterface::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $mgr = new AsyncCacheManager(cache_adapter: $cache, rate_limiter: null, logger: new NullLogger(), lock_factory: $lockFactory);

        $tags = ['tag1','tag2'];
        $cache->expects($this->exactly(2))->method('set')->with($this->stringStartsWith('tag_v:'));
        $this->assertTrue(await($mgr->invalidateTags($tags)));
    }
}
