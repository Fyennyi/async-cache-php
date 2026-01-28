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
 * JSON-based serialization implementation.
 */
class JsonSerializer implements SerializerInterface
{
    /**
     * @param int $options Bitmask of json_encode options for tailoring JSON generation
     */
    public function __construct(
        private int $options = 0
    ) {
    }

    /**
     * @inheritDoc
     *
     * @param  mixed  $data Data to be encoded into JSON format
     * @return string The resulting JSON-encoded string
     */
    public function serialize(mixed $data): string
    {
        return json_encode($data, $this->options) ?: '';
    }

    /**
     * @inheritDoc
     *
     * @param  string $data The JSON-encoded string to decode
     * @return mixed  The decoded data structure (array or scalar)
     */
    public function unserialize(string $data): mixed
    {
        return json_decode($data, true);
    }
}
