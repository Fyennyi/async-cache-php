<?php

namespace Tests\Unit\RateLimiter;

use Fyennyi\AsyncCache\RateLimiter\InMemoryRateLimiter;
use PHPUnit\Framework\TestCase;

class InMemoryRateLimiterTest extends TestCase
{
    public function testNotLimitedByDefault() : void
    {
        $limiter = new InMemoryRateLimiter();
        $this->assertFalse($limiter->isLimited('any_key'));
    }

    public function testLimitsBasedOnInterval() : void
    {
        $limiter = new InMemoryRateLimiter();
        $limiter->configure('test_key', 2); // 2 seconds interval

        // 1. Not limited initially
        $this->assertFalse($limiter->isLimited('test_key'));

        // 2. Record execution
        $limiter->recordExecution('test_key');

        // 3. Should be limited immediately after
        $this->assertTrue($limiter->isLimited('test_key'));

        // 4. Sleep to simulate time passing (mocking time would be better, but sleep(2) is acceptable for unit here or we can override time() if namespace mocked)
        // For strict unit testing without sleep, we would usually inject a Clock interface.
        // Given the simple implementation uses global time(), we can use sleep(2) or just verify logic.
        // Let's use sleep(2) as it is robust enough for this simple implementation.

        sleep(2);

        $this->assertFalse($limiter->isLimited('test_key'));
    }
    
    public function testIndependentKeys() : void
    {
        $limiter = new InMemoryRateLimiter();
        $limiter->configure('key1', 10);
        $limiter->configure('key2', 10);

        $limiter->recordExecution('key1');

        $this->assertTrue($limiter->isLimited('key1'));
        $this->assertFalse($limiter->isLimited('key2'));
    }
}
