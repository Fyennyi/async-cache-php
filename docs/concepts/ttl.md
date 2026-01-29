# Cache TTL vs Grace Period

To implement resilient caching, the library separates the logical expiration from the physical existence of the data.

## `ttl` (Logical Expiration)

- **Definition**: The duration (in seconds) during which data is considered "fresh".
- **Behavior**: Within this time, the library will always return the cached value and **not** call your data factory.

## `stale_grace_period` (Physical Expiration)

- **Definition**: The total duration the data is kept in the physical cache (e.g., in Redis or Filesystem).
- **Behavior**: Should be significantly larger than `ttl`.
- **Relationship**: `physical_ttl = ttl + stale_grace_period`.

### Why separate them?

If `ttl` is 60 and `stale_grace_period` is 3600 (1 hour):

- **0-60s**: User gets fresh data.
- **60s-1h**: User gets fresh data if the API is available, but gets **stale** data if the API is rate-limited.
- **After 1h**: Data is deleted from the cache.
