# Basic Setup

To use the Async Cache Manager, you need two components: a PSR-16 cache implementation and a Rate Limiter.

## Initialize the Cache

The library works with any PSR-16 compliant cache. If you're using Symfony, you can use their `Psr16Cache` adapter:

```php
<?php

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

$psr16Cache = new Psr16Cache(new FilesystemAdapter());
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
