<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Middleware\CoalesceMiddleware;
use Fyennyi\AsyncCache\Middleware\RetryMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use function React\Async\await;

class MiddlewareTest extends TestCase
{
    public function testRetryMiddlewareRetries() : void
    {
        $middleware = new RetryMiddleware(max_retries: 2, initial_delay_ms: 1, logger: new NullLogger());
        $context = new CacheContext('k', fn () => null, new CacheOptions());

        $failCount = 0;
        $next = function () use (&$failCount) {
            $d = new Deferred();
            if ($failCount < 2) {
                $failCount++;
                $d->reject(new \Exception('fail'));
            } else {
                $d->resolve('ok');
            }

            return $d->promise();
        };

        $res = await($middleware->handle($context, $next));
        $this->assertSame('ok', $res);
        $this->assertSame(2, $failCount);
    }

    public function testCoalesceMiddleware() : void
    {
        $middleware = new CoalesceMiddleware();
        $context = new CacheContext('k', fn () => null, new CacheOptions());

        $deferred = new Deferred();
        $callCount = 0;
        $next = function () use ($deferred, &$callCount) {
            $callCount++;

            return $deferred->promise();
        };

        $p1 = $middleware->handle($context, $next);
        $p2 = $middleware->handle($context, $next);

        $this->assertSame($p1, $p2);
        $this->assertSame(1, $callCount);

        $deferred->resolve('done');
        $this->assertSame('done', await($p1));
    }
}
