# API Reference

## `AsyncCacheBuilder`

The recommended way to create the manager.

### `create(PsrCacheInterface|ReactCacheInterface|AsyncCacheAdapterInterface $cache_adapter): self`
Starts the builder with the given cache adapter.

### `withRateLimiter(LimiterInterface $rate_limiter): self`
Configures a Symfony Rate Limiter.

### `withLogger(LoggerInterface $logger): self`
Sets a PSR-3 logger.

### `build(): AsyncCacheManager`
Finalizes and returns the manager.

## `AsyncCacheManager`

### `wrap(string $key, callable $promise_factory, CacheOptions $options): Future`
Wraps an operation with caching logic.

### `increment(string $key, int $step = 1, ?CacheOptions $options = null): Future`
Atomically increments a cached value.

### `decrement(string $key, int $step = 1, ?CacheOptions $options = null): Future`
Atomically decrements a cached value.

### `invalidateTags(array $tags): Future`
Invalidates items by tags.

## `CacheOptions`

| Property | Type | Default | Description |
| :--- | :--- | :--- | :--- |
| `ttl` | `?int` | `3600` | Logical expiration in seconds. |
| `strategy` | `CacheStrategy` | `Strict` | Caching strategy (Strict, Background, ForceRefresh). |
| `stale_grace_period` | `int` | `86400` | Physical storage TTL in seconds. |
| `rate_limit_key` | `?string` | `null` | Key for rate limiting logic. |
| `serve_stale_if_limited`| `bool` | `true` | Return stale data on rate limit hits. |
| `tags` | `array` | `[]` | Tags for invalidation. |
| `x_fetch_beta` | `float` | `1.0` | X-Fetch algorithm coefficient. |
| `compression` | `bool` | `false` | Enable Zlib compression. |
