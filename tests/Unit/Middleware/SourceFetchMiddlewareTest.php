<?php

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Middleware\SourceFetchMiddleware;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use React\Promise\Deferred;
use function React\Async\await;

class SourceFetchMiddlewareTest extends TestCase
{
    public function testSourceFetchFetchesAndCaches() : void
    {
        $storage = $this->createMock(CacheStorage::class);
        $middleware = new SourceFetchMiddleware($storage, new NullLogger());

        $context = new CacheContext('k', fn () => 'fresh', new CacheOptions());

        $storage->expects($this->once())
            ->method('set')
            ->with('k', 'fresh')
            ->willReturn((new Deferred())->promise());

        $res = await($middleware->handle($context, function () {
            return \React\Promise\resolve('fresh');
        }));

        $this->assertSame('fresh', $res);
    }

    public function testSourceFetchHandlesException() : void
    {
        $storage = $this->createMock(CacheStorage::class);
        $middleware = new SourceFetchMiddleware($storage, new NullLogger());

        $context = new CacheContext('k', fn () => throw new \Exception('oops'), new CacheOptions());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('oops');

        await($middleware->handle($context, function () {
            return \React\Promise\reject(new \Exception('oops'));
        }));
    }
}
