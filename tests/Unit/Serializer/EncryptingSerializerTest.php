<?php

namespace Fyennyi\AsyncCache\Serializer;

/**
 * Shadowing openssl_encrypt to simulate encryption failures.
 */
function openssl_encrypt($data, $cipher_algo, $passphrase, $options = 0, $iv = '', &$tag = null, $aad = '', $tag_length = 16)
{
    if (isset($GLOBALS['mock_openssl_encrypt_fail']) && $GLOBALS['mock_openssl_encrypt_fail']) {
        return false;
    }

    return \openssl_encrypt($data, $cipher_algo, $passphrase, $options, $iv, $tag, $aad, $tag_length);
}

namespace Tests\Unit\Serializer;

use Fyennyi\AsyncCache\Serializer\EncryptingSerializer;
use Fyennyi\AsyncCache\Serializer\PhpSerializer;
use PHPUnit\Framework\TestCase;

class EncryptingSerializerTest extends TestCase
{
    private string $key;
    private EncryptingSerializer $serializer;

    protected function setUp() : void
    {
        // 32 characters = 32 bytes for AES-256
        $this->key = '12345678901234567890123456789012';
        $this->serializer = new EncryptingSerializer(new PhpSerializer(), $this->key);
    }

    protected function tearDown() : void
    {
        $GLOBALS['mock_openssl_encrypt_fail'] = false;
    }

    public function testEncryptDecrypt() : void
    {
        $data = ['foo' => 'bar'];
        $serialized = $this->serializer->serialize($data);

        $this->assertIsString($serialized);
        $this->assertNotSame(serialize($data), $serialized);

        $unserialized = $this->serializer->unserialize($serialized);
        $this->assertSame($data, $unserialized);
    }

    public function testThrowsOnInvalidKey() : void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EncryptingSerializer(new PhpSerializer(), 'too-short');
    }

    public function testThrowsOnEncryptionFailure() : void
    {
        $GLOBALS['mock_openssl_encrypt_fail'] = true;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encryption failed');

        $this->serializer->serialize('some data');
    }

    public function testUnserializeThrowsOnCorruptData() : void
    {
        // Valid base64 but not valid encrypted payload
        $corrupt = base64_encode('random-data-that-is-not-json-or-encrypted');
        $this->expectException(\RuntimeException::class);
        $this->serializer->unserialize($corrupt);
    }

    public function testUnserializeWithInvalidBase64() : void
    {
        $this->expectException(\RuntimeException::class);
        $this->serializer->unserialize('!!!not-base64!!!');
    }

    public function testUnserializeWithTooShortData() : void
    {
        $this->expectException(\RuntimeException::class);
        $this->serializer->unserialize(base64_encode('short'));
    }

    public function testUnserializeThrowsOnDecryptFailure() : void
    {
        // Valid base64, correct format (JSON with data/iv/tag), but invalid encryption
        $payload = base64_encode(json_encode([
            'data' => base64_encode('garbage'),
            'iv' => base64_encode(str_repeat('i', 16)),
            'tag' => base64_encode(str_repeat('t', 16)),
        ]));

        $this->expectException(\Throwable::class);
        $this->serializer->unserialize($payload);
    }

    public function testUnserializeThrowsOnInnerSerializerFailure() : void
    {
        $inner = $this->createMock(\Fyennyi\AsyncCache\Serializer\SerializerInterface::class);
        $inner->method('unserialize')->willThrowException(new \Exception('Inner fail'));

        $enc = new EncryptingSerializer($inner, $this->key);

        $data = $enc->serialize('some data');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Inner fail');
        $enc->unserialize($data);
    }
}
