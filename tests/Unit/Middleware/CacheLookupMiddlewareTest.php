<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use Fyennyi\AsyncCache\Middleware\CacheLookupMiddleware;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use function React\Async\await;

class CacheLookupMiddlewareTest extends TestCase
{
    private MockObject|CacheStorage $storage;
    private CacheLookupMiddleware $middleware;

    protected function setUp() : void
    {
        $this->storage = $this->createMock(CacheStorage::class);
        $this->middleware = new CacheLookupMiddleware($this->storage, new NullLogger());
    }

    public function testReturnsCachedDataIfFresh() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $item = new CachedItem('val', time() + 100);

        $d = new Deferred();
        $d->resolve($item);
        $this->storage->method('get')->willReturn($d->promise());

        // Next should not be called
        $next = fn () => (new Deferred())->promise();

        $this->assertSame('val', await($this->middleware->handle($context, $next)));
    }

    public function testCallsNextOnCacheMiss() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());

        $d = new Deferred();
        $d->resolve(null);
        $this->storage->method('get')->willReturn($d->promise());

        $next = function () {
            $d = new Deferred();
            $d->resolve('fetched');

            return $d->promise();
        };

        $this->assertSame('fetched', await($this->middleware->handle($context, $next)));
    }

    public function testBypassesOnForceRefresh() : void
    {
        $options = new CacheOptions(strategy: CacheStrategy::ForceRefresh);
        $context = new CacheContext('k', fn () => null, $options);

        $this->storage->expects($this->never())->method('get');

        $next = function () {
            $d = new Deferred();
            $d->resolve('fetched');

            return $d->promise();
        };

        $this->assertSame('fetched', await($this->middleware->handle($context, $next)));
    }

    public function testBackgroundRefreshReturnsStaleAndCallsNext() : void
    {
        $options = new CacheOptions(strategy: CacheStrategy::Background);
        $context = new CacheContext('k', fn () => null, $options);

        // Stale item
        $item = new CachedItem('stale', time() - 100);
        $d = new Deferred();
        $d->resolve($item);
        $this->storage->method('get')->willReturn($d->promise());

        $called = false;
        $next = function () use (&$called) {
            $called = true;
            $d = new Deferred();
            $d->resolve('fresh');

            return $d->promise();
        };

        // Should return stale data immediately
        $this->assertSame('stale', await($this->middleware->handle($context, $next)));

        // But next() should have been called (background fetch)
        $this->assertTrue($called);
    }
}
