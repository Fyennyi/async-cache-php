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
 * Standard PHP serialization implementation.
 */
class PhpSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     *
     * @param  mixed  $data Data to be serialized using native PHP serialization
     * @return string Serialized PHP string package
     */
    public function serialize(mixed $data) : string
    {
        return serialize($data);
    }

    /**
     * @inheritDoc
     *
     * @param  string $data The string to be unserialized
     * @return mixed  The original data structure
     */
    public function unserialize(string $data) : mixed
    {
        return unserialize($data);
    }
}
