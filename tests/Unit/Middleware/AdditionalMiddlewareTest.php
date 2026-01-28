<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Core\Deferred;
use Fyennyi\AsyncCache\Middleware\SourceFetchMiddleware;
use Fyennyi\AsyncCache\Middleware\StaleOnErrorMiddleware;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AdditionalMiddlewareTest extends TestCase
{
    public function testSourceFetchFetchesAndCaches(): void
    {
        $storage = $this->createMock(CacheStorage::class);
        $middleware = new SourceFetchMiddleware($storage, new NullLogger());

        $context = new CacheContext('k', fn () => 'fresh', new CacheOptions());

        $storage->expects($this->once())
            ->method('set')
            ->with('k', 'fresh')
            ->willReturn((new Deferred())->future()); // set returns Future

        $res = $middleware->handle($context, fn () => null)->wait();
        $this->assertSame('fresh', $res);
    }

    public function testSourceFetchHandlesException(): void
    {
        $storage = $this->createMock(CacheStorage::class);
        $middleware = new SourceFetchMiddleware($storage, new NullLogger());

        $context = new CacheContext('k', fn () => throw new \Exception('oops'), new CacheOptions());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('oops');

        $middleware->handle($context, fn () => null)->wait();
    }

    public function testStaleOnErrorReturnsStaleOnFailure(): void
    {
        $middleware = new StaleOnErrorMiddleware(new NullLogger());

        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $context->stale_item = new CachedItem('stale', time() - 10);

        $next = function () {
            $d = new Deferred();
            $d->reject(new \Exception('fail'));
            return $d->future();
        };

        $res = $middleware->handle($context, $next)->wait();
        $this->assertSame('stale', $res);
    }

    public function testStaleOnErrorRejectsIfNoStale(): void
    {
        $middleware = new StaleOnErrorMiddleware(new NullLogger());

        $context = new CacheContext('k', fn () => null, new CacheOptions());
        // No stale item

        $next = function () {
            $d = new Deferred();
            $d->reject(new \Exception('fail'));
            return $d->future();
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('fail');

        $middleware->handle($context, $next)->wait();
    }
}
