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

namespace Fyennyi\AsyncCache\Core;

use React\Promise\PromiseInterface;

/**
 * High-level timer for non-blocking asynchronous delays
 */
class Timer
{
    /**
     * Creates a non-blocking delay that resolves into a Promise
     *
     * @param  float  $seconds  The number of seconds to wait before resolving the promise
     * @return PromiseInterface A promise that resolves after the specified delay
     */
    public static function delay(float $seconds) : PromiseInterface
    {
        if (function_exists('React\Promise\Timer\resolve')) {
            return \React\Promise\Timer\resolve($seconds);
        }

        throw new \RuntimeException("ReactPHP Timer is not available. Check your installation.");
    }
}
