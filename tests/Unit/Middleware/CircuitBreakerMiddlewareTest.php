<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Core\Deferred;
use Fyennyi\AsyncCache\Middleware\CircuitBreakerMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class CircuitBreakerMiddlewareTest extends TestCase
{
    private MockObject|CacheInterface $storage;
    private CircuitBreakerMiddleware $middleware;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(CacheInterface::class);
        $this->middleware = new CircuitBreakerMiddleware(
            $this->storage,
            failure_threshold: 2,
            retry_timeout: 60,
            logger: new NullLogger()
        );
    }

    public function testAllowsRequestWhenClosed(): void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $this->storage->method('get')->willReturn('closed');

        $next = function () {
            $d = new Deferred();
            $d->resolve('ok');
            return $d->future();
        };

        $this->assertSame('ok', $this->middleware->handle($context, $next)->wait());
    }

    public function testBlocksRequestWhenOpenAndFresh(): void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $this->storage->method('get')->willReturnMap([
            ['cb:k:state', 'closed', 'open'],
            ['cb:k:last_failure', 0, time()] // failed just now
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circuit Breaker is OPEN');

        // Next should not be called, but providing a dummy just in case
        $next = fn () => (new Deferred())->future();
        $this->middleware->handle($context, $next)->wait();
    }

    public function testAllowsProbeWhenOpenAndExpired(): void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $this->storage->method('get')->willReturnMap([
            ['cb:k:state', 'closed', 'open'],
            ['cb:k:last_failure', 0, time() - 61] // failed long ago
        ]);

        $this->storage->expects($this->exactly(3))->method('set')->willReturnCallback(function ($k, $v) {
            if ('cb:k:state' === $k && 'half_open' === $v) {
                return true;
            }
            if ('cb:k:state' === $k && 'closed' === $v) {
                return true;
            }
            if ('cb:k:failures' === $k && 0 === $v) {
                return true;
            }
            return true;
        });

        $next = function () {
            $d = new Deferred();
            $d->resolve('ok');
            return $d->future();
        };

        $this->assertSame('ok', $this->middleware->handle($context, $next)->wait());
    }

    public function testRecordsFailureAndOpensCircuit(): void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $this->storage->method('get')->willReturnMap([
            ['cb:k:state', 'closed', 'closed'],
            ['cb:k:failures', 0, 1] // already 1 failure
        ]);

        $called = 0;
        $this->storage->method('set')->willReturnCallback(function ($key, $val) use (&$called) {
            $called++;
            if (1 === $called) {
                $this->assertSame('cb:k:failures', $key);
            }
            if (2 === $called) {
                $this->assertSame('cb:k:state', $key);
            }
            return true;
        });

        $next = function () {
            $d = new Deferred();
            $d->reject(new \Exception('fail'));
            return $d->future();
        };

        try {
            $this->middleware->handle($context, $next)->wait();
        } catch (\Exception $e) {
        }

        $this->assertGreaterThanOrEqual(2, $called);
    }

    public function testResetsOnSuccess(): void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $this->storage->method('get')->willReturn('closed');

        $this->storage->method('set')->willReturnCallback(function ($k, $v) {
            if ('cb:k:state' === $k) {
                $this->assertSame('closed', $v);
            }
            if ('cb:k:failures' === $k) {
                $this->assertSame(0, $v);
            }
            return true;
        });

        $next = function () {
            $d = new Deferred();
            $d->resolve('ok');
            return $d->future();
        };

        $this->middleware->handle($context, $next)->wait();
    }
}
