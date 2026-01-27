<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Core\Deferred;
use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Middleware\RetryMiddleware;
use Fyennyi\AsyncCache\Middleware\CoalesceMiddleware;
use Fyennyi\AsyncCache\Middleware\StaleOnErrorMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MiddlewareTest extends TestCase
{
    public function testRetryMiddlewareRetries() : void
    {
        $middleware = new RetryMiddleware(max_retries: 2, initial_delay_ms: 1, logger: new NullLogger());
        $context = new CacheContext('k', fn()=>null, new CacheOptions());
        
        $failCount = 0;
        $next = function() use (&$failCount) {
            $d = new Deferred();
            if ($failCount < 2) {
                $failCount++;
                $d->reject(new \Exception('fail'));
            } else {
                $d->resolve('success');
            }
            return $d->future();
        };

        $res = $middleware->handle($context, $next)->wait();
        $this->assertSame('success', $res);
        $this->assertSame(2, $failCount);
    }

    public function testCoalesceMiddleware() : void
    {
        $middleware = new CoalesceMiddleware();
        $context = new CacheContext('k', fn()=>null, new CacheOptions());

        $deferred = new Deferred();
        $callCount = 0;
        $next = function() use ($deferred, &$callCount) {
            $callCount++;
            return $deferred->future();
        };

        // Two concurrent calls
        $f1 = $middleware->handle($context, $next);
        $f2 = $middleware->handle($context, $next);

        // Only one call to next
        $this->assertSame(1, $callCount);

        $deferred->resolve('val');
        $this->assertSame('val', $f1->wait());
        $this->assertSame('val', $f2->wait());
    }
}
