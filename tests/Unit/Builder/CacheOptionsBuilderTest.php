<?php

namespace Tests\Unit\Builder;

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\CacheOptionsBuilder;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use PHPUnit\Framework\TestCase;

class CacheOptionsBuilderTest extends TestCase
{
    public function testBuildsWithDefaults() : void
    {
        $options = CacheOptionsBuilder::create()->build();

        $this->assertInstanceOf(CacheOptions::class, $options);
        $this->assertSame(3600, $options->ttl);
    }

    public function testBuildsWithCustomValues() : void
    {
        $options = CacheOptionsBuilder::create()
            ->withTtl(60)
            ->withStaleGracePeriod(120)
            ->withRateLimit('key', false)
            ->withCompression(true, 512)
            ->withFailSafe(false)
            ->withXFetch(0.5)
            ->withTags(['a', 'b'])
            ->withStrategy(CacheStrategy::Strict)
            ->build();

        $this->assertSame(60, $options->ttl);
        $this->assertSame(120, $options->stale_grace_period);
        $this->assertSame('key', $options->rate_limit_key);
        $this->assertFalse($options->serve_stale_if_limited);
        $this->assertTrue($options->compression);
        $this->assertSame(512, $options->compression_threshold);
        $this->assertFalse($options->fail_safe);
        $this->assertSame(0.5, $options->x_fetch_beta);
        $this->assertSame(['a', 'b'], $options->tags);
        $this->assertSame(CacheStrategy::Strict, $options->strategy);
    }

    public function testWithStrategy() : void
    {
        $options1 = CacheOptionsBuilder::create()->withStrategy(CacheStrategy::Background)->build();
        $this->assertSame(CacheStrategy::Background, $options1->strategy);

        $options2 = CacheOptionsBuilder::create()->withStrategy(CacheStrategy::ForceRefresh)->build();
        $this->assertSame(CacheStrategy::ForceRefresh, $options2->strategy);

        $options3 = CacheOptionsBuilder::create()->withStrategy(CacheStrategy::Strict)->build();
        $this->assertSame(CacheStrategy::Strict, $options3->strategy);
    }
}
