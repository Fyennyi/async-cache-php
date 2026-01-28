<?php

namespace Fyennyi\AsyncCache\Middleware;

function microtime($as_float = false)
{
    if ($as_float && !empty($GLOBALS['mock_lock_timeout'])) {
        $val = $GLOBALS['mock_lock_value'] ?? 2000000000.0;
        $GLOBALS['mock_lock_value'] = $val + 11.0;
        return $val;
    }
    return \microtime($as_float);
}

/**
 * Shadowing delay to trigger retry errors.
 */
function delay($seconds)
{
    if (!empty($GLOBALS['mock_delay_fail'])) {
        return \React\Promise\reject(new \Exception('Delay failed'));
    }
    return \React\Promise\Timer\resolve($seconds);
}

namespace Tests\Unit\Middleware;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Middleware\AsyncLockMiddleware;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use function React\Async\await;

class AsyncLockMiddlewareTest extends TestCase
{
    private MockObject|CacheStorage $storage;
    private LockFactory $lockFactory;
    private AsyncLockMiddleware $middleware;

    protected function setUp() : void
    {
        $this->storage = $this->createMock(CacheStorage::class);
        $this->lockFactory = new LockFactory(new InMemoryStore());
        $this->middleware = new AsyncLockMiddleware($this->lockFactory, $this->storage, new NullLogger());
    }

    protected function tearDown() : void
    {
        $GLOBALS['mock_lock_timeout'] = false;
        $GLOBALS['mock_delay_fail'] = false;
        unset($GLOBALS['mock_lock_value']);
    }

    public function testAcquiresLockAndProceeds() : void
    {
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $this->assertSame('ok', await($this->middleware->handle($context, fn () => \React\Promise\resolve('ok'))));
    }

    public function testReturnsStaleIfLockedAndStaleAvailable() : void
    {
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $context->stale_item = new CachedItem('stale', time() - 10);
        $this->assertSame('stale', await($this->middleware->handle($context, fn () => null)));
    }

    public function testLockWaitAndSuccess() : void
    {
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();
        \React\EventLoop\Loop::addTimer(0.01, fn () => $lock->release());
        $context = new CacheContext('k', fn () => null, new CacheOptions());
        $this->storage->method('get')->willReturn(\React\Promise\resolve(null));
        $this->assertSame('ok', await($this->middleware->handle($context, fn () => \React\Promise\resolve('ok'))));
    }

    public function testLockTimeout() : void
    {
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();
        $GLOBALS['mock_lock_timeout'] = true;
        $this->expectException(\RuntimeException::class);
        await($this->middleware->handle(new CacheContext('k', fn () => null, new CacheOptions()), fn () => null));
    }

    public function testHandleWithLockSyncException() : void
    {
        $this->expectException(\Exception::class);
        await($this->middleware->handle(new CacheContext('k', fn () => null, new CacheOptions()), function () {
            throw new \Exception('Sync fail');
        }));
    }

    public function testLockStorageError() : void
    {
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();
        \React\EventLoop\Loop::addTimer(0.01, fn () => $lock->release());
        $this->storage->method('get')->willReturn(\React\Promise\reject(new \Exception('Storage error')));
        $this->expectException(\Exception::class);
        await($this->middleware->handle(new CacheContext('k', fn () => null, new CacheOptions()), fn () => null));
    }

    public function testReleaseLockTwice() : void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('warning');
        $middleware = new AsyncLockMiddleware($this->lockFactory, $this->storage, $logger);
        $ref = new \ReflectionClass($middleware);
        $method = $ref->getMethod('releaseLock');
        $method->setAccessible(true);
        $method->invoke($middleware, 'nonexistent');
        $this->assertTrue(true);
    }

    public function testHandleWithLockAlreadyFresh() : void
    {
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();
        \React\EventLoop\Loop::addTimer(0.01, fn () => $lock->release());
        $this->storage->method('get')->willReturn(\React\Promise\resolve(new CachedItem('fresh', time() + 100)));
        $res = await($this->middleware->handle(new CacheContext('k', fn () => null, new CacheOptions()), fn () => null));
        $this->assertSame('fresh', $res);
    }

    public function testHandleAsyncExceptionLog() : void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('debug');
        $middleware = new AsyncLockMiddleware($this->lockFactory, $this->storage, $logger);
        $this->expectException(\Exception::class);
        await($middleware->handle(new CacheContext('k', fn () => null, new CacheOptions()), fn () => \React\Promise\reject(new \Exception('Async fail'))));
    }

    public function testLockInnerErrorCatch() : void
    {
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();
        \React\EventLoop\Loop::addTimer(0.01, fn () => $lock->release());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('error')->with($this->stringContains('LOCK_INNER_ERROR'));

        $middleware = new AsyncLockMiddleware($this->lockFactory, $this->storage, $logger);
        $this->storage->method('get')->willReturn(\React\Promise\resolve(null));

        $context = new CacheContext('k', fn () => null, new CacheOptions());
        // Next throws synchronously INSIDE handleWithLock to trigger the inner catch
        // Actually, to trigger the catch on inner_promise->then(),
        // one of the callbacks in then() must throw.

        // Let's use a proxy next that throws
        $next = function () {
            throw new \Error('Inner sync throw');
        };

        $this->expectException(\Throwable::class);
        await($middleware->handle($context, $next));
    }

    public function testLockStorageErrorCatch() : void
    {
        $lock = $this->lockFactory->createLock('lock:k');
        $lock->acquire();
        \React\EventLoop\Loop::addTimer(0.01, fn () => $lock->release());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('error')->with($this->stringContains('LOCK_STORAGE_ERROR'));

        $middleware = new AsyncLockMiddleware($this->lockFactory, $this->storage, $logger);

        $this->storage->method('get')->willReturn(\React\Promise\reject(new \Exception('Storage failed')));

        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Storage failed');
        await($middleware->handle(new CacheContext('k', fn () => null, new CacheOptions()), fn () => null));
    }

    public function testRetryErrorCatch() : void
    {
        $lock = $this->createMock(\Symfony\Component\Lock\SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);

        $lockFactory = $this->createMock(\Symfony\Component\Lock\LockFactory::class);
        $callCount = 0;
        $lockFactory->method('createLock')->willReturnCallback(function () use (&$callCount, $lock) {
            $callCount++;
            if (2 === $callCount) {
                throw new \RuntimeException('Forced retry failure');
            }
            return $lock;
        });

        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('LOCK_RETRY_ERROR'));

        $middleware = new AsyncLockMiddleware($lockFactory, $this->storage, $logger);

        $context = new CacheContext('k', fn () => null, new CacheOptions());

        // Використовуємо handle прямо і запускаємо Loop, щоб дати час асинхронним подіям
        $promise = $middleware->handle($context, fn () => null);

        try {
            await($promise);
        } catch (\Throwable $e) {
            // Очікуване виключення
            $this->assertSame('Forced retry failure', $e->getMessage());
        }
    }
}
