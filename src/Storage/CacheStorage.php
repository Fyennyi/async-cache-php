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
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\Clock\NativeClock;
use function React\Promise\all;

/**
 * High-level storage orchestrator that manages serialization and tag metadata.
 */
class CacheStorage
{
    private const TAG_PREFIX = 'tag_v:';
    private SerializerInterface $serializer;
    private ClockInterface $clock;

    /**
     * @param AsyncCacheAdapterInterface $adapter    The asynchronous cache adapter
     * @param LoggerInterface            $logger     Logger for reporting errors and debug info
     * @param SerializerInterface|null   $serializer Custom serializer implementation
     * @param ClockInterface|null        $clock      PSR-20 Clock implementation
     */
    public function __construct(
        private AsyncCacheAdapterInterface $adapter,
        private LoggerInterface $logger,
        ?SerializerInterface $serializer = null,
        ?ClockInterface $clock = null
    ) {
        $this->serializer = $serializer ?? new PhpSerializer();
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * Retrieves an item from the cache and handles basic integrity checks.
     *
     * @param  string                  $key     The cache key identifier to retrieve
     * @param  CacheOptions            $options Configuration options for fail-safe retrieval
     * @return PromiseInterface<mixed>
     */
    public function get(string $key, CacheOptions $options) : PromiseInterface
    {
        return $this->adapter->get($key)->then(
            function ($cached_item) use ($key) {
                if (null === $cached_item) {
                    return null;
                }

                // Handle backward compatibility (old array format)
                if (is_array($cached_item) && array_key_exists('d', $cached_item) && array_key_exists('e', $cached_item)) {
                    $e = $cached_item['e'];
                    $cached_item = new CachedItem($cached_item['d'], is_numeric($e) ? (int) $e : 0);
                }

                if (! $cached_item instanceof CachedItem) {
                    return null;
                }

                return $this->processDecompression($cached_item, $key);
            },
            function (\Throwable $e) use ($key, $options) {
                if ($options->fail_safe) {
                    $msg = $e->getMessage();
                    $this->logger->error('AsyncCache CACHE_GET_ERROR', ['key' => $key, 'error' => $msg]);

                    return null;
                }
                throw $e;
            }
        );
    }

    /**
     * Stores an item in the cache asynchronously.
     *
     * @param  string                 $key             The cache key identifier
     * @param  mixed                  $data            The raw data value to store
     * @param  CacheOptions           $options         Configuration for TTL, tags and compression
     * @param  float                  $generation_time Time taken to generate the data in seconds
     * @return PromiseInterface<bool>
     */
    public function set(string $key, mixed $data, CacheOptions $options, float $generation_time = 0.0) : PromiseInterface
    {
        $physical_ttl = $options->ttl + $options->stale_grace_period;

        /** @param array<string, string> $tag_versions */
        $prepare_item = function (array $tag_versions) use ($data, $options, $generation_time) {
            /** @var array<string, string> $tag_versions */
            $is_compressed = false;
            if ($options->compression) {
                $serialized_data = $this->serializer->serialize($data);
                if (strlen($serialized_data) >= $options->compression_threshold) {
                    $compressed_data = @gzcompress($serialized_data);
                    if (false !== $compressed_data) {
                        $data = $compressed_data;
                        $is_compressed = true;
                    }
                }
            }

            return new CachedItem(
                data: $data,
                logical_expire_time: $this->clock->now()->getTimestamp() + $options->ttl,
                is_compressed: $is_compressed,
                generation_time: $generation_time,
                tag_versions: $tag_versions
            );
        };

        if (! empty($options->tags)) {
            return $this->fetchTagVersions($options->tags, true)->then(function ($tag_versions) use ($key, $physical_ttl, $prepare_item) {
                $item = $prepare_item($tag_versions);

                return $this->adapter->set($key, $item, $physical_ttl)->then(fn () => $item);
            });
        }

        $item = $prepare_item([]);

        return $this->adapter->set($key, $item, $physical_ttl)->then(fn () => $item);
    }

    /**
     * Invalidates specific tags asynchronously.
     *
     * @param  string[]               $tags List of tag names to invalidate
     * @return PromiseInterface<bool> Resolving to true on successful invalidation of all tags
     */
    public function invalidateTags(array $tags) : PromiseInterface
    {
        if (empty($tags)) {
            return \React\Promise\resolve(true);
        }

        $promises = [];
        foreach ($tags as $tag) {
            $promises[] = $this->adapter->set(self::TAG_PREFIX . $tag, $this->generateVersion(), 86400 * 30);
        }

        return all($promises)->then(fn () => true);
    }

    /**
     * Fetches current versions of specified tags.
     *
     * @param  string[]                               $tags           List of tag names to fetch
     * @param  bool                                   $create_missing Whether to generate versions for missing tags
     * @return PromiseInterface<array<string,string>> Resolving to map of tag names to their versions
     */
    public function fetchTagVersions(array $tags, bool $create_missing = false) : PromiseInterface
    {
        if (empty($tags)) {
            return \React\Promise\resolve([]);
        }

        $keys = array_map(fn ($t) => self::TAG_PREFIX . $t, $tags);

        return $this->adapter->getMultiple($keys)->then(function ($raw_versions) use ($tags, $create_missing) {
            $versions = [];
            $set_promises = [];
            $raw_versions = is_array($raw_versions) ? $raw_versions : [];

            foreach ($tags as $tag) {
                $version = $raw_versions[self::TAG_PREFIX . $tag] ?? null;
                if (null === $version && $create_missing) {
                    $version = $this->generateVersion();
                    $set_promises[] = $this->adapter->set(self::TAG_PREFIX . $tag, $version, 86400 * 30);
                }
                $versions[$tag] = (\is_scalar($version) || $version instanceof \Stringable) ? (string) $version : '';
            }

            if (empty($set_promises)) {
                return $versions;
            }

            return all($set_promises)->then(fn () => $versions);
        });
    }

    /**
     * Handles decompression of cached data if needed.
     *
     * @param  CachedItem              $item The cached item to process
     * @param  string                  $key  Cache key for logging purposes
     * @return PromiseInterface<mixed>
     */
    private function processDecompression(CachedItem $item, string $key) : PromiseInterface
    {
        if (! $item->is_compressed) {
            return \React\Promise\resolve($item);
        }

        try {
            /** @var string $compressed_data */
            $compressed_data = $item->data;
            $serialized_data = @gzuncompress($compressed_data);

            if (false === $serialized_data) {
                throw new \RuntimeException('Failed to decompress cached data');
            }

            $data = $this->serializer->unserialize($serialized_data);

            return \React\Promise\resolve(new CachedItem(
                data: $data,
                logical_expire_time: $item->logical_expire_time,
                is_compressed: false,
                version: $item->version,
                generation_time: $item->generation_time,
                tag_versions: $item->tag_versions
            ));
        } catch (\Throwable $e) {
            $this->logger->error('AsyncCache DECOMPRESSION_ERROR', ['key' => $key, 'error' => $e->getMessage()]);

            return \React\Promise\resolve(null);
        }
    }

    /**
     * Generates a unique version string for tags.
     *
     * @return string Unique version identifier
     */
    private function generateVersion() : string
    {
        return uniqid('', true);
    }
}
