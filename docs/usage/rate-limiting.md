# Rate Limiting

The library integrates with **Symfony Rate Limiter** to manage how often the data source is contacted when cache entries expire.

## Integration

The library uses `Symfony\Component\RateLimiter\RateLimiterFactoryInterface`. This allows the manager to create specific limiters dynamically based on the `rate_limit_key` provided in the options.

### Example with Symfony Rate Limiter

```php
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$factory = new RateLimiterFactory([
    'id' => 'my_api',
    'policy' => 'token_bucket',
    'limit' => 5,
    'rate' => ['interval' => '10 seconds'],
], new InMemoryStorage());

// Pass the factory, not a specific limiter
$manager = new AsyncCacheManager(
    AsyncCacheManager::configure($cache)
        ->withRateLimiter($factory)
        ->build()
);
```

## How It Interacts with Cache

When a cache item is stale and a refresh is needed:
1. The manager checks if a `rate_limit_key` is provided in `CacheOptions`.
2. It uses the factory to create/get a limiter for that key: `$factory->create($rate_limit_key)`.
3. It calls `->consume(1)` on that limiter.
4. If **Accepted**: The pipeline continues to fetch fresh data.
5. If **Rejected**: 
    - If `serve_stale_if_limited` is **true** and stale data exists in the context, the stale data is returned immediately.
    - Otherwise, a `RateLimitException` is thrown.

## Manual Reset

If you need to manually reset the limit for a specific key (e.g. after an administrative action), use the manager:

```php
$manager->resetRateLimit('user_123');
```

## Benefit of Symfony Integration

By using Symfony Rate Limiter, you gain access to various storage backends (Redis, Database, PHP-APC) and sophisticated policies (Token Bucket, Fixed Window, Sliding Window) without additional configuration in this library.
