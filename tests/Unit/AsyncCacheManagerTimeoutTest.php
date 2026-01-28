<?php

namespace Fyennyi\AsyncCache;

// Shadowing microtime to simulate timeout in increment()
function microtime($as_float = false)
{
    if ($as_float && !empty($GLOBALS['mock_microtime_timeout'])) {
        $val = $GLOBALS['mock_microtime_value'] ?? 2000000000.0;
        $GLOBALS['mock_microtime_value'] = $val + 5.0; // Increment by 5s each call
        return $val;
    }
    return \microtime($as_float);
}

namespace Tests\Unit;

use Fyennyi\AsyncCache\AsyncCacheManager;
use Fyennyi\AsyncCache\Storage\AsyncCacheAdapterInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

class AsyncCacheManagerTimeoutTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['mock_microtime_timeout'] = false;
        unset($GLOBALS['mock_microtime_value']);
    }

    public function testIncrementTimeout(): void
    {
        $adapter = $this->createMock(AsyncCacheAdapterInterface::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $lockFactory = $this->createMock(LockFactory::class);

        $lockFactory->method('createLock')->willReturn($lock);
        // First call to acquire(false) returns false, triggers timeout logic
        $lock->method('acquire')->willReturn(false);

        $mgr = new AsyncCacheManager($adapter, lock_factory: $lockFactory);

        $GLOBALS['mock_microtime_timeout'] = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not acquire lock for incrementing key');

        $mgr->increment('k')->wait();
    }
}
