<?php

namespace Tests\Unit\Exception;

use Fyennyi\AsyncCache\Exception\RateLimitException;
use PHPUnit\Framework\TestCase;

class RateLimitExceptionTest extends TestCase
{
    public function testMessageIncludesKey(): void
    {
        $key = 'my_test_key';
        $exception = new RateLimitException($key);

        $this->assertStringContainsString($key, $exception->getMessage());
        $this->assertSame("Rate limit exceeded for key: my_test_key", $exception->getMessage());
    }
}
