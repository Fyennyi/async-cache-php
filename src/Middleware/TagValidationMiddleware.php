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
 * Middleware responsible for validating tag versions of cached items.
 */
class TagValidationMiddleware implements MiddlewareInterface
{
    /**
     * @param CacheStorage    $storage The storage orchestrator to fetch tag versions
     * @param LoggerInterface $logger  Logging implementation for debug info
     */
    public function __construct(
        private CacheStorage $storage,
        private LoggerInterface $logger
    ) {}

    /**
     * @template T
     * @inheritDoc
     *
     * @param  CacheContext                               $context The current request context
     * @param  callable(CacheContext):PromiseInterface<T> $next    The next middleware in the chain
     * @return PromiseInterface<T>                        A promise representing the eventual result
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $item = $context->stale_item;

        if (! $item instanceof CachedItem || empty($item->tag_versions)) {
            return $next($context);
        }

        $this->logger->debug('TAG_VALIDATION_START: Validating tags', ['key' => $context->key, 'tags' => array_keys($item->tag_versions)]);

        $tags = array_map('strval', array_keys($item->tag_versions));

        /** @var PromiseInterface<array<string, string>> $versions_promise */
        $versions_promise = $this->storage->fetchTagVersions($tags);

        return $versions_promise->then(
            function (array $current_versions) use ($context, $item, $next) {
                foreach ($item->tag_versions as $tag => $saved_version) {
                    if (($current_versions[$tag] ?? null) !== $saved_version) {
                        $this->logger->debug('TAG_INVALID: Version mismatch for tag', [
                            'key' => $context->key,
                            'tag' => $tag,
                            'saved' => $saved_version,
                            'current' => $current_versions[$tag] ?? 'null',
                        ]);
                        $context->stale_item = null;

                        return $next($context);
                    }
                }

                $this->logger->debug('TAG_VALID: All tags are valid', ['key' => $context->key]);

                // Tags are valid. If the item is fresh, we can short-circuit and return it.
                if ($item->isFresh($context->clock->now()->getTimestamp())) {
                    /** @var T $item_data */
                    $item_data = $item->data;

                    return \React\Promise\resolve($item_data);
                }

                // Item is stale but tags are valid, continue to StrategyMiddleware
                return $next($context);
            },
            function (\Throwable $e) use ($context, $next) {
                $this->logger->error('TAG_FETCH_ERROR: Failed to fetch tag versions', ['key' => $context->key, 'error' => $e->getMessage()]);
                // On tag fetch error, we conservatively treat as stale/invalid
                $context->stale_item = null;

                return $next($context);
            }
        );
    }
}
