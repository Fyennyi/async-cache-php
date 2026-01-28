<?php

/*
 *
 *     _                          ____           _            ____  _   _ ____
 *    / \   ___ _   _ _ __   ___ / ___|__ _  ___| |__   ___  |  _ \| | | |  _ \
 *   / _ \ / __| | | | '_ \ / __| |   / _` |/ __| '_ \ / _ \ | |_) | |_| | |_) |
 *  / ___ \\__ \ |_| | | | | (__| |__| (_| | (__| | | |  __/ |  __/|  _  |  __/
 * /_/   \_\___/\__, |_| |_|\___|\____\__,_|\___|_| |_|\___| |_|   |_| |_|_|
 *              |___/
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author Serhii Cherneha
 * @link https://chernega.eu.org/
 *
 *
 */

namespace Fyennyi\AsyncCache\Storage;

use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use React\Cache\CacheInterface as ReactCacheInterface;
use function React\Promise\all;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

/**
 * Asynchronous adapter that chains multiple cache layers (L1, L2, L3...)
 */
class ChainCacheAdapter implements AsyncCacheAdapterInterface
{
    /** @var AsyncCacheAdapterInterface[] Ordered list of asynchronous adapters */
    private array $adapters = [];

     /**
      * @param  AsyncCacheAdapterInterface[]  $adapters  Ordered list of adapters (Psr, React or Async)
      */
    public function __construct(array $adapters)
    {
        foreach ($adapters as $adapter) {
            if ($adapter instanceof AsyncCacheAdapterInterface) {
                $this->adapters[] = $adapter;
            } elseif ($adapter instanceof PsrCacheInterface) {
                $this->adapters[] = new PsrToAsyncAdapter($adapter);
            } elseif ($adapter instanceof ReactCacheInterface) {
                $this->adapters[] = new ReactCacheAdapter($adapter);
            }
        }
    }

    /**
     * Retrieves an item from the first layer that has it, then backfills upper layers
     *
     * @param  string  $key  The unique key of this item in the cache
     * @return PromiseInterface Resolves to the cached value or null on miss
     */
    public function get(string $key) : PromiseInterface
    {
        return $this->resolveLayer($key, 0);
    }

    /**
     * Recursive resolution of cache layers with asynchronous backfilling
     *
     * @param  string    $key       Cache key to find
     * @param  int       $index     Current layer index in the adapters array
     * @return PromiseInterface
     */
    private function resolveLayer(string $key, int $index) : PromiseInterface
    {
        if (! isset($this->adapters[$index])) {
            return resolve(null);
        }

        return $this->adapters[$index]->get($key)->then(
            function ($value) use ($key, $index) {
                if ($value !== null) {
                    // Backfill: populate all faster layers above this one asynchronously
                    for ($i = 0; $i < $index && isset($this->adapters[$i]); $i++) {
                        $this->adapters[$i]->set($key, $value);
                    }
                    return $value;
                }

                // Try next layer in the hierarchy
                return $this->resolveLayer($key, $index + 1);
            },
            function () use ($key, $index) {
                // If a layer fails critically, we still attempt the next one for resilience
                return $this->resolveLayer($key, $index + 1);
            }
        );
    }

    /**
     * Obtains multiple cache items by their unique keys
     *
     * @param  iterable<string>  $keys  A list of keys that can be obtained in a single operation
     * @return PromiseInterface         Resolves to an array of key => value pairs
     */
    public function getMultiple(iterable $keys) : PromiseInterface
    {
        $results = [];
        $promises = [];
        /** @var array<string> $keys_array */
        $keys_array = is_array($keys) ? $keys : iterator_to_array($keys);

        if (empty($keys_array)) {
            return resolve([]);
        }

        foreach ($keys_array as $key) {
            $promises[$key] = $this->get($key);
        }

        return all($promises);
    }

    /**
     * Persists data in all cache layers concurrently
     *
     * @param  string    $key    The key of the item to store
     * @param  mixed     $value  The value of the item to store
     * @param  int|null  $ttl    Optional. The TTL value of this item
     * @return PromiseInterface  Resolves to true on success
     */
    public function set(string $key, mixed $value, ?int $ttl = null) : PromiseInterface
    {
        if (empty($this->adapters)) {
            return resolve(true);
        }

        $promises = [];
        foreach ($this->adapters as $adapter) {
            $promises[] = $adapter->set($key, $value, $ttl);
        }

        return all($promises)->then(fn() => true);
    }

    /**
     * Deletes an item from all cache layers concurrently
     *
     * @param  string  $key  The unique cache key of the item to delete
     * @return PromiseInterface
     */
    public function delete(string $key) : PromiseInterface
    {
        if (empty($this->adapters)) {
            return resolve(true);
        }

        $promises = [];
        foreach ($this->adapters as $adapter) {
            $promises[] = $adapter->delete($key);
        }

        return all($promises)->then(fn() => true);
    }

    /**
     * Wipes clean all cache layers concurrently
     *
     * @return PromiseInterface
     */
    public function clear() : PromiseInterface
    {
        if (empty($this->adapters)) {
            return resolve(true);
        }

        $promises = [];
        foreach ($this->adapters as $adapter) {
            $promises[] = $adapter->clear();
        }

        return all($promises)->then(fn() => true);
    }
}
