<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Exception\RateLimitException;
use Fyennyi\AsyncCache\Middleware\RateLimitMiddleware;
use Fyennyi\AsyncCache\Model\CachedItem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use function React\Async\await;
use function React\Promise\resolve;

class RateLimitMiddlewareTest extends TestCase
{
    private MockObject|LoggerInterface $logger;
    private MockObject|EventDispatcherInterface $dispatcher;
    private MockObject|RateLimiterFactoryInterface $factory;
    private MockObject|LimiterInterface $limiter;
    private MockClock $clock;
    private RateLimitMiddleware $middleware;

    protected function setUp() : void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->factory = $this->createMock(RateLimiterFactoryInterface::class);
        $this->limiter = $this->createMock(LimiterInterface::class);
        $this->clock = new MockClock();
        $this->middleware = new RateLimitMiddleware($this->factory, $this->logger, $this->dispatcher);
    }

    public function testBypassesIfNoLimiterOrNoKey() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions(), $this->clock);
        $next = fn () => resolve('ok');

        $this->assertSame('ok', await($this->middleware->handle($context, $next)));

        $middlewareNoLimiter = new RateLimitMiddleware(null, $this->logger);
        $this->assertSame('ok', await($middlewareNoLimiter->handle($context, $next)));
    }

    public function testCallsNextIfAccepted() : void
    {
        $key = 'api_limit';
        $context = new CacheContext('k', fn () => null, new CacheOptions(rate_limit_key: $key), $this->clock);
        $next = fn () => resolve('ok');

        $rateLimit = new RateLimit(10, new \DateTimeImmutable(), true, 10);

        $this->factory->expects($this->once())->method('create')->with($key)->willReturn($this->limiter);
        $this->limiter->expects($this->once())->method('consume')->willReturn($rateLimit);

        $this->assertSame('ok', await($this->middleware->handle($context, $next)));
    }

    public function testThrowsExceptionIfExceededAndNoStale() : void
    {
        $key = 'api_limit';
        $context = new CacheContext('k', fn () => null, new CacheOptions(rate_limit_key: $key, serve_stale_if_limited: false), $this->clock);
        $next = fn () => resolve('ok');

        $rateLimit = new RateLimit(0, new \DateTimeImmutable(), false, 10);

        $this->factory->method('create')->willReturn($this->limiter);
        $this->limiter->method('consume')->willReturn($rateLimit);

        $this->expectException(RateLimitException::class);
        await($this->middleware->handle($context, $next));
    }

    public function testReturnsStaleIfExceededAndConfigured() : void
    {
        $key = 'api_limit';
        $item = new CachedItem('stale_data', $this->clock->now()->getTimestamp() - 10);
        $context = new CacheContext('k', fn () => null, new CacheOptions(rate_limit_key: $key, serve_stale_if_limited: true), $this->clock);
        $context->stale_item = $item;
        $next = fn () => resolve('should_not_be_called');

        $rateLimit = new RateLimit(0, new \DateTimeImmutable(), false, 10);

        $this->factory->method('create')->willReturn($this->limiter);
        $this->limiter->method('consume')->willReturn($rateLimit);

        $this->assertSame('stale_data', await($this->middleware->handle($context, $next)));
    }
}
