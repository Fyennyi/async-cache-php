# Wrapping Operations

The core of the library is the `wrap` method. It allows you to wrap any asynchronous operation (a factory that returns a value or a `PromiseInterface`) with caching logic.

## The `wrap` Method

```php
public function wrap(
    string $key, 
    callable $factory, 
    CacheOptions $options
): Future
```

### Example: Wrapping a Guzzle Request

```php
<?php

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Enum\CacheStrategy;
use GuzzleHttp\Client;

$client = new Client();

// Configure how this specific request should be cached
$options = new CacheOptions(
    ttl: 60,                        // Data is fresh for 60 seconds
    strategy: CacheStrategy::Strict // Default strategy
);

$future = $manager->wrap(
    'user_profile_1',
    fn() => $client->getAsync('https://api.example.com/users/1'),
    $options
);

// wait() will resolve the promise (non-blocking if in event loop)
$response = $future->wait();
```

## Cache Options

The `CacheOptions` DTO provides granular control over the caching behavior:

- **`ttl`**: Time in seconds the data is considered fresh.
- **`strategy`**: The caching strategy to use (from `CacheStrategy` enum).
    - `Strict`: Fetches fresh data if stale.
    - `Background`: Returns stale data immediately and refreshes in background.
    - `ForceRefresh`: Bypasses cache lookup.
- **`stale_grace_period`**: How long to keep expired data in the cache (physical TTL).
- **`rate_limit_key`**: Identifier for rate limiting logic.
- **`serve_stale_if_limited`**: Whether to return expired data when rate limited.
- **`tags`**: Tags for cache invalidation.
- **`x_fetch_beta`**: Beta coefficient for X-Fetch algorithm.
