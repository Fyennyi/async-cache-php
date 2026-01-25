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

## Setup the Rate Limiter

The Rate Limiter prevents the library from making too many requests to your data source when the cache expires.

```php
<?php

use Fyennyi\AsyncCache\RateLimiter\InMemoryRateLimiter;

$rateLimiter = new InMemoryRateLimiter();

// Allow 1 request every 5 seconds for the 'my_api' identifier
$rateLimiter->configure('my_api', 5);
```

## Create the Manager

Finally, instantiate the `AsyncCacheManager`:

```php
<?php

use Fyennyi\AsyncCache\AsyncCacheManager;

$manager = new AsyncCacheManager($psr16Cache, $rateLimiter);
```
