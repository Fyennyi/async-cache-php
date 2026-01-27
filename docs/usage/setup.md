# Basic Setup

To use the Async Cache Manager, you need two components: a PSR-16 cache implementation and a Rate Limiter.

## Initialize the Cache

The library works with any PSR-16 compliant cache. Here are examples using popular adapters from Symfony Cache:

=== "Filesystem"

    ```php
    use Symfony\Component\Cache\Adapter\FilesystemAdapter;
    use Symfony\Component\Cache\Psr16Cache;

    $psr16Cache = new Psr16Cache(new FilesystemAdapter());
    ```

=== "Redis"

    ```php
    use Symfony\Component\Cache\Adapter\RedisAdapter;
    use Symfony\Component\Cache\Psr16Cache;

    $redisClient = new \Redis();
    $redisClient->connect('127.0.0.1');

    $psr16Cache = new Psr16Cache(new RedisAdapter($redisClient));
    ```

=== "Memcached"

    ```php
    use Symfony\Component\Cache\Adapter\MemcachedAdapter;
    use Symfony\Component\Cache\Psr16Cache;

    $memcached = new \Memcached();
    $memcached->addServer('127.0.0.1', 11211);

    $psr16Cache = new Psr16Cache(new MemcachedAdapter($memcached));
    ```

## Setup the Rate Limiter (Optional)

The library integrates with **Symfony Rate Limiter**. You can pass any `LimiterInterface` to the manager.

```php
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

$factory = new RateLimiterFactory([
    'id' => 'my_api',
    'policy' => 'fixed_window',
    'limit' => 10,
    'interval' => '1 minute',
], new InMemoryStorage());

$limiter = $factory->create();
```

## Create the Manager

The recommended way to instantiate the `AsyncCacheManager` is using the `AsyncCacheBuilder`:

```php
<?php

use Fyennyi\AsyncCache\AsyncCacheBuilder;

$manager = AsyncCacheBuilder::create($psr16Cache)
    ->withRateLimiter($limiter) // Optional
    ->withLogger($logger)       // Optional (PSR-3)
    ->build();
```
