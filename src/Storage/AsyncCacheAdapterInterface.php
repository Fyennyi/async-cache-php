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
 * Common interface for all asynchronous cache storage backends
 */
interface AsyncCacheAdapterInterface
{
    /**
     * Retrieves an item by its key
     *
     * @param  string  $key  The unique identifier of the item
     * @return PromiseInterface Resolving to the raw value or null if not found
     */
    public function get(string $key) : PromiseInterface;

    /**
     * Retrieves multiple items by their keys
     *
     * @param  iterable<string>  $keys  A list of keys to retrieve
     * @return PromiseInterface         Resolving to an associative array of key => value pairs
     */
    public function getMultiple(iterable $keys) : PromiseInterface;

    /**
     * Persists an item in the cache
     *
     * @param  string    $key    The unique identifier of the item
     * @param  mixed     $value  The value to store (must be serializable)
     * @param  int|null  $ttl    Optional time to live in seconds
     * @return PromiseInterface Resolving to true on success, false otherwise
     */
    public function set(string $key, mixed $value, ?int $ttl = null) : PromiseInterface;

    /**
     * Removes an item by its key
     *
     * @param  string  $key  The unique identifier of the item to remove
     * @return PromiseInterface Resolving to true on success, false otherwise
     */
    public function delete(string $key) : PromiseInterface;

    /**
     * Wipes all entries from the cache
     *
     * @return PromiseInterface Resolving to true on success, false otherwise
     */
    public function clear() : PromiseInterface;
}
