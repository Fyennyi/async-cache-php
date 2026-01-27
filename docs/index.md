# Async Cache PHP

[![Latest Stable Version](https://img.shields.io/packagist/v/fyennyi/async-cache-php.svg?label=Packagist&logo=packagist)](https://packagist.org/packages/fyennyi/async-cache-php)
[![License](https://img.shields.io/packagist/l/fyennyi/async-cache-php.svg?label=Licence&logo=open-source-initiative)](https://packagist.org/packages/fyennyi/async-cache-php)

An asynchronous caching abstraction layer for PHP with built-in rate limiting and stale-while-revalidate support.

## Overview

`fyennyi/async-cache-php` is designed to wrap promise-based operations (like Guzzle Promises) to provide robust caching strategies suitable for high-load or rate-limited API clients.

It solves the common problem of handling expired cache items when the underlying data source (e.g., an external API) is currently rate-limited or slow.

## Key Features

- **Asynchronous Caching**: Wraps `PromiseInterface` or any callable to handle caching transparently without blocking execution.
- **Stale-While-Revalidate**: Supports background revalidation and stale-on-error patterns.
- **X-Fetch Algorithm**: Prevents cache stampedes (dog-pile effect) via probabilistic early recomputation.
- **Atomic Operations**: Support for atomic `increment` and `decrement` operations using Symfony Lock.
- **Rate Limiting Integration**: Built-in support for Symfony Rate Limiter for request throttling.
- **Logical vs. Physical TTL**: Separates the "freshness" of data from its "existence" in the cache.
- **Universal Adapters**: Works with PSR-16, ReactPHP Cache, or native Async adapters.
