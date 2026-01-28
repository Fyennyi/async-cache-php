<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Middleware\AsyncLockMiddleware;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use function React\Async\await;

class AsyncLockMiddlewareTest extends TestCase
{
    private MockObject|CacheStorage $storage;
    private LockFactory $lockFactory;
    private AsyncLockMiddleware $middleware;

    protected function setUp() : void
    {
        $this->storage = $this->createMock(CacheStorage::class);
        $this->lockFactory = new LockFactory(new InMemoryStore());
        $this->middleware = new AsyncLockMiddleware(
            $this->lockFactory,
            $this->storage,
            new NullLogger()
        );
    }

    public function testAcquiresLockAndProceeds() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());

        $next = function () {
            $d = new Deferred();
            $d->resolve('ok');

            return $d->promise();
        };

        $this->assertSame('ok', await($this->middleware->handle($context, $next)));
    }

    public function testReturnsStaleIfLockedAndStaleAvailable() : void
    {
        // Acquire lock manually first to simulate busy
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();

        $item = new CachedItem('stale', time() - 10);
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $context->stale_item = $item;

        // Next should not be called
        $next = fn () => (new Deferred())->promise(); // dummy

        $this->assertSame('stale', await($this->middleware->handle($context, $next)));
    }
}
