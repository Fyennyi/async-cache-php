# Async Cache PHP

An asynchronous caching abstraction layer for PHP with built-in rate limiting and stale-while-revalidate support.

## Overview

`fyennyi/async-cache-php` is designed to wrap promise-based operations (like Guzzle Promises) to provide robust caching strategies suitable for high-load or rate-limited API clients.

It solves the common problem of handling expired cache items when the underlying data source (e.g., an external API) is currently rate-limited or slow.

## Key Features

- **Asynchronous Caching**: Wraps `PromiseInterface` to handle caching transparently without blocking execution.
- **Stale-While-Limited Strategy**: If the rate limit is hit, the library can return stale data (if available) instead of failing.
- **Logical vs. Physical TTL**: Separates the "freshness" of data from its "existence" in the cache.
- **Rate Limiting Interface**: Includes a simple in-memory rate limiter and an interface for persistent implementations.
- **PSR-16 Compliant**: Works with any PSR-16 Simple Cache adapter.
