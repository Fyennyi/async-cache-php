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

namespace Fyennyi\AsyncCache\Serializer;

/**
 * High-performance serializer using the igbinary PHP extension.
 */
class IgbinarySerializer implements SerializerInterface
{
    /**
     * @throws \RuntimeException If the igbinary extension is not loaded in the PHP environment
     */
    public function __construct()
    {
        if (! extension_loaded('igbinary')) {
            throw new \RuntimeException('igbinary extension is not loaded');
        }
    }

    /**
     * @inheritDoc
     *
     * @param  mixed  $data Data to be serialized using the igbinary binary format
     * @return string Serialized binary data string
     */
    public function serialize(mixed $data): string
    {
        if (! function_exists('\igbinary_serialize')) {
            throw new \RuntimeException('igbinary extension is not loaded');
        }
        return \igbinary_serialize($data) ?: '';
    }

    /**
     * @inheritDoc
     *
     * @param  string $data The binary-encoded string to be unserialized
     * @return mixed  The original data structure
     */
    public function unserialize(string $data): mixed
    {
        if (! function_exists('\igbinary_unserialize')) {
            throw new \RuntimeException('igbinary extension is not loaded');
        }
        return \igbinary_unserialize($data);
    }
}
