<?php

namespace Tests\Unit;

use Fyennyi\AsyncCache\CacheOptions;
use PHPUnit\Framework\TestCase;

class CacheOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new CacheOptions();

        $this->assertNull($options->ttl);
        $this->assertNull($options->rate_limit_key);
        $this->assertTrue($options->serve_stale_if_limited);
        $this->assertSame(86400, $options->stale_grace_period);
        $this->assertFalse($options->force_refresh);
        $this->assertSame([], $options->tags);
    }

    public function testCustomValues(): void
    {
        $options = new CacheOptions(
            ttl: 300,
            rate_limit_key: 'test_key',
            serve_stale_if_limited: false,
            stale_grace_period: 60,
            force_refresh: true,
            tags: ['tag1', 'tag2']
        );

        $this->assertSame(300, $options->ttl);
        $this->assertSame('test_key', $options->rate_limit_key);
        $this->assertFalse($options->serve_stale_if_limited);
        $this->assertSame(60, $options->stale_grace_period);
        $this->assertTrue($options->force_refresh);
        $this->assertSame(['tag1', 'tag2'], $options->tags);
    }
}
