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
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use function React\Async\await;

class CacheLookupMiddlewareTest extends TestCase
{
    private MockObject|CacheStorage $storage;
    private MockObject|LoggerInterface $logger;
    private MockClock $clock;
    private CacheLookupMiddleware $middleware;

    protected function setUp() : void
    {
        $this->storage = $this->createMock(CacheStorage::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->clock = new MockClock();
        $this->middleware = new CacheLookupMiddleware($this->storage, $this->logger);
    }

    public function testSetsStaleItemAndCallsNextIfFound() : void
    {
        $item = new CachedItem('data', $this->clock->now()->getTimestamp() + 100);
        $context = new CacheContext('k', fn () => null, new CacheOptions(), $this->clock);
        $this->storage->method('get')->willReturn(\React\Promise\resolve($item));
        $next = fn () => \React\Promise\resolve('from_next');

        $res = await($this->middleware->handle($context, $next));

        $this->assertSame('from_next', $res);
        $this->assertSame($item, $context->stale_item);
    }

    public function testCallsNextOnCacheMiss() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions(), $this->clock);
        $this->storage->method('get')->willReturn(\React\Promise\resolve(null));
        $next = fn () => \React\Promise\resolve('from_next');

        $this->assertSame('from_next', await($this->middleware->handle($context, $next)));
        $this->assertNull($context->stale_item);
    }

    public function testBypassesOnForceRefresh() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions(strategy: CacheStrategy::ForceRefresh), $this->clock);
        $this->storage->expects($this->never())->method('get');
        $next = fn () => \React\Promise\resolve('bypassed');

        $this->assertSame('bypassed', await($this->middleware->handle($context, $next)));
    }

    public function testXFetchTriggered() : void
    {
        $item = new CachedItem('data', $this->clock->now()->getTimestamp() + 1, generation_time: 1.0);
        $context = new CacheContext('k', fn () => null, new CacheOptions(x_fetch_beta: 1000.0), $this->clock);
        $this->storage->method('get')->willReturn(\React\Promise\resolve($item));
        $next = fn () => \React\Promise\resolve('xfetch_triggered');

        $res = await($this->middleware->handle($context, $next));

        $this->assertSame('xfetch_triggered', $res);
        $this->assertNotNull($context->stale_item);
        $this->assertFalse($context->stale_item->isFresh($this->clock->now()->getTimestamp()));
    }

    public function testHandlesStorageError() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions(), $this->clock);
        $this->storage->method('get')->willReturn(\React\Promise\reject(new \Exception('Storage error')));
        $next = fn () => \React\Promise\resolve('fallback');

        $this->assertSame('fallback', await($this->middleware->handle($context, $next)));
    }
}
