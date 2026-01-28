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

use Fyennyi\AsyncCache\CacheOptions;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Serializer\PhpSerializer;
use Fyennyi\AsyncCache\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * High-level storage orchestrator that manages serialization and tag metadata
 */
class CacheStorage
{
    private const TAG_PREFIX = 'tag_v:';
    private SerializerInterface $serializer;

    /**
     * @param  AsyncCacheAdapterInterface  $adapter     The asynchronous cache adapter
     * @param  LoggerInterface             $logger      Logger for reporting errors and debug info
     * @param  SerializerInterface|null    $serializer  Custom serializer implementation
     */
    public function __construct(
        private AsyncCacheAdapterInterface $adapter,
        private LoggerInterface $logger,
        ?SerializerInterface $serializer = null
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
    }

    /**
     * Retrieves an item from the cache and handles basic integrity checks
     *
     * @param  string        $key      The cache key identifier to retrieve
     * @param  CacheOptions  $options  Configuration options for fail-safe retrieval
     * @return PromiseInterface        Resolves to CachedItem object or null if not found/invalid
     */
    public function get(string $key, CacheOptions $options) : PromiseInterface
    {
        return $this->adapter->get($key)->then(
            function ($cached_item) use ($key) {
                if ($cached_item === null) {
                    return null;
                }

                // Handle backward compatibility (old array format)
                if (is_array($cached_item) && array_key_exists('d', $cached_item) && array_key_exists('e', $cached_item)) {
                    $e = $cached_item['e'];
                    $cached_item = new CachedItem($cached_item['d'], is_numeric($e) ? (int)$e : 0);
                }

                if (! $cached_item instanceof CachedItem) {
                    return null;
                }

                return $this->processDecompression($cached_item, $key);
            },
            function ($e) use ($key, $options) {
                if ($options->fail_safe) {
                    $msg = $e instanceof \Throwable ? $e->getMessage() : (\is_scalar($e) || $e instanceof \Stringable ? (string)$e : 'Unknown error');
                    $this->logger->error('AsyncCache CACHE_GET_ERROR', ['key' => $key, 'error' => $msg]);
                    return null;
                }
                throw $e;
            }
        );
    }

    /**
     * Stores an item in the cache asynchronously
     *
     * @param  string        $key              The cache key identifier
     * @param  mixed         $data             The raw data value to store
     * @param  CacheOptions  $options          Configuration for TTL, tags and compression
     * @param  float         $generation_time  Time taken to generate the data in seconds
     * @return PromiseInterface                Resolves to true on successful storage
     */
    public function set(string $key, mixed $data, CacheOptions $options, float $generation_time = 0.0) : PromiseInterface
    {
        $physical_ttl = $options->ttl + $options->stale_grace_period;

        $prepare_item = function (array $tag_versions) use ($data, $options, $generation_time) {
            $is_compressed = false;
            if ($options->compression) {
                $serialized_data = $this->serializer->serialize($data);
                if (strlen($serialized_data) >= $options->compression_threshold) {
                    $compressed_data = @gzcompress($serialized_data);
                    if ($compressed_data !== false) {
                        $data = $compressed_data;
                        $is_compressed = true;
                    }
                }
            }

            return new CachedItem(
                data: $data,
                logical_expire_time: time() + $options->ttl,
                is_compressed: $is_compressed,
                generation_time: $generation_time,
                tag_versions: $tag_versions
            );
        };

        if (! empty($options->tags)) {
            return $this->fetchTagVersions($options->tags, true)->then(function ($tag_versions) use ($key, $physical_ttl, $prepare_item) {
                $item = $prepare_item($tag_versions);
                return $this->adapter->set($key, $item, $physical_ttl);
            });
        }

        return $this->adapter->set($key, $prepare_item([]), $physical_ttl);
    }

     /**
      * Invalidates specific tags asynchronously
      *
      * @param  string[]  $tags  List of tag names to invalidate
      * @return PromiseInterface Resolving to true on successful invalidation of all tags
      */
    public function invalidateTags(array $tags) : PromiseInterface
    {
        if (empty($tags)) {
            return \React\Promise\resolve(true);
        }

        $promises = [];
        foreach ($tags as $tag) {
            $promises[] = $this->adapter->set(self::TAG_PREFIX . $tag, $this->generateVersion());
        }

        return all($promises)->then(function() use ($tags) {
            $this->logger->info('AsyncCache TAGS_INVALIDATED', ['tags' => $tags]);
            return true;
        });
    }

    /**
     * Internal helper for decompression
     *
     * @param  CachedItem  $cached_item  The cached item instance to decompress if needed
     * @param  string      $key          The cache key for logging purposes
     * @return CachedItem|null           The decompressed item object or null on error
     */
    private function processDecompression(CachedItem $cached_item, string $key) : ?CachedItem
    {
        if ($cached_item->is_compressed && is_string($cached_item->data)) {
            $decompressed_data = @gzuncompress($cached_item->data);
            if ($decompressed_data !== false) {
                $data = $this->serializer->unserialize($decompressed_data);
                return new CachedItem(
                    data: $data,
                    logical_expire_time: $cached_item->logical_expire_time,
                    version: $cached_item->version,
                    is_compressed: false,
                    generation_time: $cached_item->generation_time,
                    tag_versions: $cached_item->tag_versions
                );
            }
            $this->logger->error('AsyncCache DECOMPRESSION_ERROR', ['key' => $key]);
            return null;
        }
        return $cached_item;
    }

     /**
      * Fetches current versions for a set of tags asynchronously
      *
      * @param  string[]  $tags            List of tag names to fetch
      * @param  bool      $create_missing  Whether to initialize missing tags with a new unique version
      * @return PromiseInterface           Resolves to an associative array of tag => version pairs
      */
    public function fetchTagVersions(array $tags, bool $create_missing = false) : PromiseInterface
    {
        if (empty($tags)) {
            return \React\Promise\resolve([]);
        }

        $keys = array_map(fn($t) => self::TAG_PREFIX . $t, $tags);
        return $this->adapter->getMultiple($keys)->then(function ($raw_versions) use ($tags, $create_missing) {
            $versions = [];
            $set_promises = [];
            $raw_versions = is_array($raw_versions) ? $raw_versions : [];

            foreach ($tags as $tag) {
                $version = $raw_versions[self::TAG_PREFIX . $tag] ?? null;
                if ($version === null && $create_missing) {
                    $version = $this->generateVersion();
                    $set_promises[] = $this->adapter->set(self::TAG_PREFIX . $tag, $version, 86400 * 30);
                }
                $versions[$tag] = (\is_scalar($version) || $version instanceof \Stringable) ? (string) $version : '';
            }

            if (empty($set_promises)) {
                return $versions;
            }

            return all($set_promises)->then(fn() => $versions);
        });
    }

    /**
     * Generates a unique version string for tags
     *
     * @return string A unique identifier for the tag version
     */
    private function generateVersion() : string
    {
        return uniqid('', true);
    }

    /**
     * Deletes an item from the cache by its unique key asynchronously
     *
     * @param  string  $key  The unique cache key identifier of the item to delete
     * @return PromiseInterface Resolving to true on success
     */
    public function delete(string $key) : PromiseInterface
    {
        return $this->adapter->delete($key);
    }

    /**
     * Wipes clean the entire cache's keys asynchronously
     *
     * @return PromiseInterface Resolving to true on success
     */
    public function clear() : PromiseInterface
    {
        return $this->adapter->clear();
    }

    /**
     * Returns the underlying asynchronous cache adapter
     *
     * @return AsyncCacheAdapterInterface The low-level adapter implementation
     */
    public function getAdapter() : AsyncCacheAdapterInterface
    {
        return $this->adapter;
    }
}
