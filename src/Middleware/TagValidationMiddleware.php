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

namespace Fyennyi\AsyncCache\Middleware;

use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Model\CachedItem;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

/**
 * Middleware responsible for validating tag versions of cached items
 */
class TagValidationMiddleware implements MiddlewareInterface
{
    /**
     * @param  CacheStorage     $storage  The storage orchestrator to fetch tag versions
     * @param  LoggerInterface  $logger   Logging implementation for debug info
     */
    public function __construct(
        private CacheStorage $storage,
        private LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     *
     * @param  CacheContext                             $context  The current request context
     * @param  callable(CacheContext):PromiseInterface  $next     The next middleware in the chain
     * @return PromiseInterface                                   A promise representing the eventual result
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $item = $context->stale_item;

        if (! $item instanceof CachedItem || empty($item->tag_versions)) {
            return $next($context);
        }

        $tags = array_map('strval', array_keys($item->tag_versions));

        // Use the internal getTagVersions from storage (it remains there as a helper for fetching versions)
        // We will make it public or use a wrapper in storage
        return $this->storage->fetchTagVersions($tags)->then(function ($current_versions) use ($context, $item, $next) {
            foreach ($item->tag_versions as $tag => $saved_version) {
                if (($current_versions[$tag] ?? null) !== $saved_version) {
                    $this->logger->debug('AsyncCache TAG_INVALID: Version mismatch for tag {tag} in key {key}', [
                        'key' => $context->key,
                        'tag' => $tag
                    ]);
                    $context->stale_item = null;
                    return $next($context);
                }
            }

            // Tags are valid. If the item is fresh, we can return it now.
            if ($item->isFresh()) {
                return $item->data;
            }

            return $next($context);
        });
    }
}
