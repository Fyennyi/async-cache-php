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

use React\Promise\PromiseInterface;

/**
 * Common interface for all asynchronous cache storage backends.
 */
interface AsyncCacheAdapterInterface
{
    /**
     * Retrieves an item from the cache by its unique key.
     *
     * @param  string                  $key The unique identifier of the cached item
     * @return PromiseInterface<mixed> Promise resolving to the cached value or null if not found
     */
    public function get(string $key) : PromiseInterface;

    /**
     * Retrieves multiple items from the cache by their keys.
     *
     * @param  iterable<string>                          $keys A list of keys to retrieve
     * @return PromiseInterface<iterable<string, mixed>> Promise resolving to an associative array of key => value
     */
    public function getMultiple(iterable $keys) : PromiseInterface;

    /**
     * Persists data in the cache associated with a specific key.
     *
     * @param  string                 $key   The unique identifier for the item
     * @param  mixed                  $value The data to store
     * @param  int|null               $ttl   Optional time-to-live in seconds
     * @return PromiseInterface<bool> Promise resolving to true on success
     */
    public function set(string $key, mixed $value, ?int $ttl = null) : PromiseInterface;

    /**
     * Removes an item from the cache.
     *
     * @param  string                 $key The unique identifier to remove
     * @return PromiseInterface<bool> Promise resolving to true on success
     */
    public function delete(string $key) : PromiseInterface;

    /**
     * Wipes the entire cache storage.
     *
     * @return PromiseInterface<bool> Promise resolving to true on success
     */
    public function clear() : PromiseInterface;
}
