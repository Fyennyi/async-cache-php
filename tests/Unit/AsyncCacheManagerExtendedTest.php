<?php

namespace Tests\Unit;

use Fyennyi\AsyncCache\AsyncCacheManager;
use Fyennyi\AsyncCache\Model\CachedItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use function React\Async\await;

class AsyncCacheManagerExtendedTest extends TestCase
{
    private MockObject|CacheInterface $cache;
    private MockObject|LockFactory $lockFactory;
    private AsyncCacheManager $manager;

    protected function setUp() : void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);

        $this->manager = new AsyncCacheManager(
            cache_adapter: $this->cache,
            rate_limiter: null,
            logger: new NullLogger(),
            lock_factory: $this->lockFactory
        );
    }

    public function testIncrementAcquiresLockAndUpdatesValue() : void
    {
        $key = 'counter';
        $lock = $this->createMock(SharedLockInterface::class);

        // 1. Acquire Lock
        $this->lockFactory->expects($this->once())
            ->method('createLock')
            ->with('lock:counter:' . $key)
            ->willReturn($lock);

        $lock->expects($this->once())->method('acquire')->willReturn(true);
        $lock->expects($this->once())->method('release');

        // 2. Get current value (10)
        $this->cache->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(new CachedItem(10, time() + 3600));

        // 3. Set new value (11)
        $this->cache->expects($this->once())
            ->method('set')
            ->with($key, $this->callback(function ($item) {
                return $item instanceof CachedItem && 11 === $item->data;
            }))
            ->willReturn(true);

        $result = await($this->manager->increment($key, 1));
        $this->assertSame(11, $result);
    }

    public function testIncrementInitializesValueIfMissing() : void
    {
        $key = 'counter';
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $this->lockFactory->method('createLock')->willReturn($lock);

        // Miss
        $this->cache->method('get')->willReturn(null);

        // Set 1
        $this->cache->expects($this->once())
            ->method('set')
            ->with($key, $this->callback(fn ($i) => 1 === $i->data))
            ->willReturn(true);

        $this->assertSame(1, await($this->manager->increment($key)));
    }

    public function testDecrement() : void
    {
        $key = 'counter';
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $this->lockFactory->method('createLock')->willReturn($lock);

        $this->cache->method('get')->willReturn(new CachedItem(10, time() + 3600));

        $this->cache->expects($this->once())
            ->method('set')
            ->with($key, $this->callback(fn ($i) => 5 === $i->data))
            ->willReturn(true);

        $this->assertSame(5, await($this->manager->decrement($key, 5)));
    }

    public function testInvalidateTags() : void
    {
        // CacheStorage implementation of invalidateTags loops through tags and sets versions
        // Since we wrap PSR cache, PsrToAsyncAdapter calls set()

        $tags = ['tag1', 'tag2'];

        $this->cache->expects($this->exactly(2))
            ->method('set')
            ->with($this->stringStartsWith('tag_v:'));

        $this->assertTrue(await($this->manager->invalidateTags($tags)));
    }
}
