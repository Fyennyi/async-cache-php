<?php

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
}
