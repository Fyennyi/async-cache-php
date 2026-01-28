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
 * Security decorator that encrypts data after serialization and decrypts before unserialization.
 */
class EncryptingSerializer implements SerializerInterface
{
    private const CIPHER = 'aes-256-gcm';

    /**
     * @param  SerializerInterface       $serializer The inner serializer to wrap
     * @param  string                    $key        Secret encryption key (exactly 32 bytes for AES-256)
     * @throws \InvalidArgumentException If the key length is incorrect
     */
    public function __construct(
        private SerializerInterface $serializer,
        private string $key
    ) {
        if (32 !== strlen($this->key)) {
            throw new \InvalidArgumentException("Encryption key must be exactly 32 bytes for AES-256");
        }
    }

    /**
     * Serializes and encrypts data.
     *
     * @param  mixed             $data Data to process
     * @throws \RuntimeException If encryption fails
     * @return string            Base64-encoded encrypted package (IV + TAG + Ciphertext)
     */
    public function serialize(mixed $data) : string
    {
        $plaintext = $this->serializer->serialize($data);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (false === $ciphertext) {
            throw new \RuntimeException("Encryption failed: " . openssl_error_string());
        }

        // We store IV + Tag + Ciphertext as a single string
        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts and unserializes data.
     *
     * @param  string            $data Base64-encoded encrypted package
     * @throws \RuntimeException If decryption fails or data is corrupted
     * @return mixed             Original reconstructed data
     */
    public function unserialize(string $data) : mixed
    {
        $decoded = base64_decode($data, true);
        if (false === $decoded) {
            throw new \RuntimeException("Failed to decode base64 data");
        }

        $iv_len = openssl_cipher_iv_length(self::CIPHER);
        $tag_len = 16; // Standard tag length for GCM

        $iv = substr($decoded, 0, $iv_len);
        $tag = substr($decoded, $iv_len, $tag_len);
        $ciphertext = substr($decoded, $iv_len + $tag_len);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if (false === $plaintext) {
            throw new \RuntimeException("Decryption failed. Data might be corrupted or key is invalid.");
        }

        return $this->serializer->unserialize($plaintext);
    }
}
