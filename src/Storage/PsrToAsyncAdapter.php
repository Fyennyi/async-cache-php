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
use React\Promise\PromiseInterface;

class PsrToAsyncAdapter implements AsyncCacheAdapterInterface
{
    public function __construct(private PsrCacheInterface $psr_cache) {}
    public function get(string $key) : PromiseInterface
    {
        try {
            return \React\Promise\resolve($this->psr_cache->get($key));
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
    public function getMultiple(iterable $keys) : PromiseInterface
    {
        try {
            $values = $this->psr_cache->getMultiple($keys);
            if ($values instanceof \Traversable) {
                $values = iterator_to_array($values);
            }

            $result = [];
            foreach ($values as $k => $v) {
                // Якщо Symfony повернула нам свій внутрішній "запакований" об'єкт
                if (is_object($v) && property_exists($v, 'value')) {
                    $result[$k] = $v->value;
                } else {
                    $result[$k] = $v;
                }
            }
            return \React\Promise\resolve($result);
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
    public function set(string $key, mixed $value, ?int $ttl = null) : PromiseInterface
    {
        try {
            return \React\Promise\resolve($this->psr_cache->set($key, $value, $ttl));
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
    public function delete(string $key) : PromiseInterface
    {
        try {
            return \React\Promise\resolve($this->psr_cache->delete($key));
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
    public function clear() : PromiseInterface
    {
        try {
            return \React\Promise\resolve($this->psr_cache->clear());
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
}
