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

namespace Fyennyi\AsyncCache\Bridge;

use GuzzleHttp\Promise\Promise as GuzzlePromise;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use GuzzleHttp\Promise\Utils;
use React\Promise\Deferred as ReactDeferred;
use React\Promise\PromiseInterface as ReactPromiseInterface;
use function React\Promise\resolve;

/**
 * Converts between industry-standard Promise objects and ReactPHP promises
 */
class PromiseAdapter
{
    /**
     * Converts a ReactPHP Promise to a Guzzle Promise
     *
     * @param  ReactPromiseInterface  $promise  The ReactPHP Promise instance to convert
     * @return GuzzlePromiseInterface           A Guzzle Promise that reflects the state of the original promise
     */
    public static function toGuzzle(ReactPromiseInterface $promise) : GuzzlePromiseInterface
    {
        $guzzle = new GuzzlePromise();
        $promise->then(
            fn($v) => $guzzle->resolve($v),
            fn($r) => $guzzle->reject($r)
        );
        return $guzzle;
    }

    /**
     * Converts any promise type or raw value to a ReactPHP Promise
     *
     * @param  mixed  $value  The value, ReactPHP promise, or Guzzle promise to convert
     * @return ReactPromiseInterface A ReactPHP Promise tracking the resolution of the provided value
     */
    public static function toPromise(mixed $value) : ReactPromiseInterface
    {
        if ($value instanceof ReactPromiseInterface) {
            return $value;
        }

        if ($value instanceof GuzzlePromiseInterface) {
            $deferred = new ReactDeferred();
            $value->then(
                fn($v) => $deferred->resolve($v),
                fn($r) => $deferred->reject($r)
            );

            // Ensure Guzzle's task queue is flushed
            Utils::queue()->run();

            return $deferred->promise();
        }

        // It's a raw value
        return resolve($value);
    }
}
