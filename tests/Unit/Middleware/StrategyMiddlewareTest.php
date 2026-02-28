<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use Fyennyi\AsyncCache\Middleware\StrategyMiddleware;
use Fyennyi\AsyncCache\Model\CachedItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use function React\Async\await;
use function React\Promise\resolve;

class StrategyMiddlewareTest extends TestCase
{
    private MockObject|LoggerInterface $logger;
    private MockObject|EventDispatcherInterface $dispatcher;
    private MockClock $clock;
    private StrategyMiddleware $middleware;

    protected function setUp() : void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->clock = new MockClock();
        $this->middleware = new StrategyMiddleware($this->logger, $this->dispatcher);
    }

    public function testCallsNextOnMiss() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions(), $this->clock);
        $next = fn () => resolve('from_next');

        $this->assertSame('from_next', await($this->middleware->handle($context, $next)));
    }

    public function testReturnsFreshHitImmediately() : void
    {
        $item = new CachedItem('data', $this->clock->now()->getTimestamp() + 100);
        $context = new CacheContext('k', fn () => null, new CacheOptions(), $this->clock);
        $context->stale_item = $item;
        $next = fn () => resolve('should_not_be_called');

        $this->assertSame('data', await($this->middleware->handle($context, $next)));
    }

    public function testBackgroundStrategyReturnsStaleAndRefreshesInBackground() : void
    {
        $item = new CachedItem('stale_data', $this->clock->now()->getTimestamp() - 10);
        $context = new CacheContext('k', fn () => null, new CacheOptions(strategy: CacheStrategy::Background), $this->clock);
        $context->stale_item = $item;

        $nextCalled = false;
        $next = function () use (&$nextCalled) {
            $nextCalled = true;
            return resolve('refreshed_data');
        };

        $this->assertSame('stale_data', await($this->middleware->handle($context, $next)));
        $this->assertTrue($nextCalled);
    }

    public function testStrictStrategyWaitsForFreshData() : void
    {
        $item = new CachedItem('stale_data', $this->clock->now()->getTimestamp() - 10);
        $context = new CacheContext('k', fn () => null, new CacheOptions(strategy: CacheStrategy::Strict), $this->clock);
        $context->stale_item = $item;

        $next = fn () => resolve('fresh_data');

        $this->assertSame('fresh_data', await($this->middleware->handle($context, $next)));
    }
}
