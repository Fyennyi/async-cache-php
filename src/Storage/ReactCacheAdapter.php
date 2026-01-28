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

use React\Cache\CacheInterface as ReactCacheInterface;
use React\Promise\PromiseInterface;

/**
 * Adapter for reactphp/cache to be used within AsyncCache.
 */
class ReactCacheAdapter implements AsyncCacheAdapterInterface
{
    /**
     * @param ReactCacheInterface $react_cache The ReactPHP cache implementation
     */
    public function __construct(private ReactCacheInterface $react_cache)
    {
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<mixed>
     */
    public function get(string $key): PromiseInterface
    {
        return $this->react_cache->get($key);
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<iterable<string,  mixed>>
     */
    public function getMultiple(iterable $keys): PromiseInterface
    {
        /** @var string[] $keys_array */
        $keys_array = is_array($keys) ? $keys : iterator_to_array($keys);
        /** @var PromiseInterface<array<string, mixed>> $promise */
        $promise = $this->react_cache->getMultiple($keys_array);

        return $promise;
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<bool>
     */
    public function set(string $key, mixed $value, ?int $ttl = null): PromiseInterface
    {
        return $this->react_cache->set($key, $value, $ttl);
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<bool>
     */
    public function delete(string $key): PromiseInterface
    {
        return $this->react_cache->delete($key);
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<bool>
     */
    public function clear(): PromiseInterface
    {
        return $this->react_cache->clear();
    }
}
