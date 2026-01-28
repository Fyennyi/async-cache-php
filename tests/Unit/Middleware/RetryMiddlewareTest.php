<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Middleware\RetryMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use function React\Async\await;

class RetryMiddlewareTest extends TestCase
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
}
