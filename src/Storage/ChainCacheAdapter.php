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
use React\Promise\PromiseInterface;
use function React\Promise\all;

/**
 * Asynchronous adapter that chains multiple cache layers (L1, L2, L3...).
 */
class ChainCacheAdapter implements AsyncCacheAdapterInterface
{
    /** @var AsyncCacheAdapterInterface[] Ordered list of asynchronous adapters */
    private array $adapters = [];

    /**
     * Resolve multiple promises without failing fast.
     *
     * @param  array<int, PromiseInterface<mixed>> $promises
     * @return PromiseInterface<array<int, mixed>>
     */
    private function settleAll(array $promises) : PromiseInterface
    {
        if (empty($promises)) {
            /** @var PromiseInterface<array<int, mixed>> $res */
            $res =
                \React\Promise\resolve([]);

            return $res;
        }

        $wrapped = array_map(
            static fn (PromiseInterface $p) => $p->then(
                static fn ($v) => ['status' => 'fulfilled', 'value' => $v],
                static fn ($e) => ['status' => 'rejected', 'reason' => $e]
            ),
            $promises
        );

        /** @var PromiseInterface<array<int, array{status: string, value?: mixed, reason?: mixed}>> $all */
        $all = all($wrapped);

        return $all;
    }

    /**
     * @param AsyncCacheAdapterInterface[] $adapters Ordered list of adapters (Psr, React or Async)
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
     * @inheritDoc
     *
     * @return PromiseInterface<mixed>
     */
    public function get(string $key) : PromiseInterface
    {
        return $this->resolveLayer($key, 0);
    }

    /**
     * @param  string                  $key   Identifier
     * @param  int                     $index Current adapter index
     * @return PromiseInterface<mixed>
     */
    private function resolveLayer(string $key, int $index) : PromiseInterface
    {
        if (! isset($this->adapters[$index])) {
            return \React\Promise\resolve(null);
        }

        return $this->adapters[$index]->get($key)->then(
            function ($value) use ($key, $index) {
                if (null !== $value) {
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
     * @inheritDoc
     *
     * @return PromiseInterface<iterable<string,  mixed>>
     */
    public function getMultiple(iterable $keys) : PromiseInterface
    {
        /** @var string[] $keys_array */
        $keys_array = is_array($keys) ? $keys : iterator_to_array($keys);

        if (empty($keys_array)) {
            /** @var array<string, mixed> $empty */
            $empty = [];
            /** @var PromiseInterface<array<string, mixed>> $res */
            $res = \React\Promise\resolve($empty);

            return $res;
        }

        return $this->resolveLayerMultiple($keys_array, 0);
    }

    /**
     * Resolves multiple keys across layers with fallback and backfill.
     *
     * @param  string[]                               $keys
     * @param  int                                    $index
     * @return PromiseInterface<array<string, mixed>>
     */
    private function resolveLayerMultiple(array $keys, int $index) : PromiseInterface
    {
        if (! isset($this->adapters[$index])) {
            /** @var array<string, mixed> $empty */
            $empty = [];
            /** @var PromiseInterface<array<string, mixed>> $res */
            $res = \React\Promise\resolve($empty);

            return $res;
        }

        return $this->adapters[$index]->getMultiple($keys)->then(
            function (iterable $results) use ($keys, $index) {
                /** @var array<string, mixed> $result_map */
                $result_map = is_array($results) ? $results : iterator_to_array($results);

                $hits = [];
                $misses = [];

                foreach ($keys as $key) {
                    if (array_key_exists($key, $result_map) && null !== $result_map[$key]) {
                        $hits[$key] = $result_map[$key];
                    } else {
                        $misses[] = $key;
                    }
                }

                if (! empty($hits)) {
                    // Backfill upper layers with any hits (best-effort)
                    for ($i = 0; $i < $index && isset($this->adapters[$i]); $i++) {
                        foreach ($hits as $key => $value) {
                            $this->adapters[$i]->set($key, $value);
                        }
                    }
                }

                if (empty($misses)) {
                    return $hits;
                }

                return $this->resolveLayerMultiple($misses, $index + 1)->then(function (array $lower) use ($hits) {
                    return $hits + $lower;
                });
            },
            function () use ($keys, $index) {
                return $this->resolveLayerMultiple($keys, $index + 1);
            }
        );
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<bool>
     */
    public function set(string $key, mixed $value, ?int $ttl = null) : PromiseInterface
    {
        if (empty($this->adapters)) {
            return \React\Promise\resolve(true);
        }

        $promises = [];
        foreach ($this->adapters as $adapter) {
            $promises[] = $adapter->set($key, $value, $ttl);
        }

        return $this->settleAll($promises)->then(fn () => true);
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<bool>
     */
    public function delete(string $key) : PromiseInterface
    {
        if (empty($this->adapters)) {
            return \React\Promise\resolve(true);
        }

        $promises = [];
        foreach ($this->adapters as $adapter) {
            $promises[] = $adapter->delete($key);
        }

        return $this->settleAll($promises)->then(fn () => true);
    }

    /**
     * @inheritDoc
     *
     * @return PromiseInterface<bool>
     */
    public function clear() : PromiseInterface
    {
        if (empty($this->adapters)) {
            return \React\Promise\resolve(true);
        }

        $promises = [];
        foreach ($this->adapters as $adapter) {
            $promises[] = $adapter->clear();
        }

        return $this->settleAll($promises)->then(fn () => true);
    }
}
