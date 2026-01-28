<?php

namespace Tests\Unit;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use PHPUnit\Framework\TestCase;

class CacheOptionsTest extends TestCase
{
    public function testDefaults() : void
    {
        $options = new CacheOptions();

        $this->assertSame(3600, $options->ttl);
        $this->assertNull($options->rate_limit_key);
        $this->assertTrue($options->serve_stale_if_limited);
        $this->assertSame(86400, $options->stale_grace_period);
        $this->assertSame(CacheStrategy::Strict, $options->strategy);
        $this->assertSame([], $options->tags);
    }

    public function testCustomValues() : void
    {
        $options = new CacheOptions(
            ttl: 300,
            stale_grace_period: 60,
            serve_stale_if_limited: false,
            strategy: CacheStrategy::ForceRefresh,
            rate_limit_key: 'test_key',
            tags: ['tag1', 'tag2']
        );

        $this->assertSame(300, $options->ttl);
        $this->assertSame('test_key', $options->rate_limit_key);
        $this->assertFalse($options->serve_stale_if_limited);
        $this->assertSame(60, $options->stale_grace_period);
        $this->assertSame(CacheStrategy::ForceRefresh, $options->strategy);
        $this->assertSame(['tag1', 'tag2'], $options->tags);
    }
}
