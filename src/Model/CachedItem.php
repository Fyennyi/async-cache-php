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

namespace Fyennyi\AsyncCache\Model;

/**
 * Value object representing a cached item with its metadata.
 */
class CachedItem
{
    public const CURRENT_VERSION = 1;

    /**
     * @param mixed                  $data                The actual cached value
     * @param int                    $logical_expire_time Unix timestamp when the data becomes stale
     * @param int                    $version             Metadata schema version
     * @param bool                   $is_compressed       Whether the data is currently gzipped
     * @param float                  $generation_time     Duration in seconds taken to fetch this data
     * @param array<string,  string> $tag_versions        Map of tag names to their versions at caching time
     */
    public function __construct(
        public readonly mixed $data,
        public readonly int $logical_expire_time,
        public readonly int $version = self::CURRENT_VERSION,
        public readonly bool $is_compressed = false,
        public readonly float $generation_time = 0.0,
        public readonly array $tag_versions = []
    ) {}

    /**
     * Checks if the item is still within its logical TTL.
     *
     * @param  int|null $now Optional Unix timestamp to check against
     * @return bool     True if fresh, false if stale
     */
    public function isFresh(?int $now = null) : bool
    {
        return ($now ?? time()) < $this->logical_expire_time;
    }
}
