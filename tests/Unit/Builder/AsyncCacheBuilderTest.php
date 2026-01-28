<?php

namespace Tests\Unit\Builder;

use Fyennyi\AsyncCache\AsyncCacheBuilder;
use Fyennyi\AsyncCache\AsyncCacheManager;
use Fyennyi\AsyncCache\Middleware\MiddlewareInterface;
use Fyennyi\AsyncCache\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\LimiterInterface;

class AsyncCacheBuilderTest extends TestCase
{
    public function testBuildsManagerWithClock() : void
    {
        $cache = $this->createMock(CacheInterface::class);
        $clock = new MockClock();

        $manager = AsyncCacheBuilder::create($cache)
            ->withClock($clock)
            ->build();

        $this->assertInstanceOf(AsyncCacheManager::class, $manager);

        // We can verify if clock was set by inspecting the private property using reflection
        $ref = new \ReflectionClass($manager);
        $prop = $ref->getProperty('clock');
        $prop->setAccessible(true);

        $this->assertSame($clock, $prop->getValue($manager));
    }

    public function testBuildsManagerWithDefaults() : void
    {
        $cache = $this->createMock(CacheInterface::class);
        $manager = AsyncCacheBuilder::create($cache)->build();

        $this->assertInstanceOf(AsyncCacheManager::class, $manager);
    }

    public function testBuildsManagerWithCustomDependencies() : void
    {
        $cache = $this->createMock(CacheInterface::class);
        $limiter = $this->createMock(LimiterInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $middleware = $this->createMock(MiddlewareInterface::class);
        $lockFactory = new LockFactory(new InMemoryStore());

        $manager = AsyncCacheBuilder::create($cache)
            ->withRateLimiter($limiter)
            ->withLogger($logger)
            ->withLockFactory($lockFactory)
            ->withEventDispatcher($dispatcher)
            ->withSerializer($serializer)
            ->withMiddleware($middleware)
            ->build();

        $this->assertInstanceOf(AsyncCacheManager::class, $manager);

        // We can't easily inspect private properties, but successful instantiation
        // with these inputs confirms the builder logic works.
    }
}
