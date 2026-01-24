# Rate Limiting

The library uses rate limiting to manage how often the data source is contacted when cache entries expire.

## The `RateLimiterInterface`

All rate limiters must implement the `RateLimiterInterface`.

### `InMemoryRateLimiter`

The library provides a simple in-memory implementation suitable for CLI scripts or single-process applications.

```php
<?php

use Fyennyi\AsyncCache\RateLimiter\InMemoryRateLimiter;

$limiter = new InMemoryRateLimiter();
$limiter->configure('api_endpoint', 10); // 1 request per 10 seconds
```

## How It Interacts with Cache

When a cache item is requested:
1. If **Fresh**: Data is returned immediately.
2. If **Stale**:
    - The manager calls `$limiter->isLimited($key)`.
    - If **Limited**: Returns stale data (if `serve_stale_if_limited` is true).
    - If **Not Limited**: Calls the factory to get fresh data and resets the limiter.

## Custom Implementations

For distributed systems (like web servers behind a load balancer), you should implement a persistent rate limiter using Redis or a database.

```php
use Fyennyi\AsyncCache\RateLimiter\RateLimiterInterface;

class RedisRateLimiter implements RateLimiterInterface {
    // Implement your logic here
}
```
