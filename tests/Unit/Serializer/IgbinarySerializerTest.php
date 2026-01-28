<?php

namespace Tests\Unit\Serializer;

use Fyennyi\AsyncCache\Serializer\IgbinarySerializer;
use PHPUnit\Framework\TestCase;

class IgbinarySerializerTest extends TestCase
{
    protected function setUp() : void
    {
        if (!extension_loaded('igbinary')) {
            $this->markTestSkipped('igbinary extension is not loaded');
        }
    }

    public function testSerializeUnserialize() : void
    {
        $serializer = new IgbinarySerializer();
        $data = ['a' => 'b', 'c' => 1];

        $serialized = $serializer->serialize($data);
        $this->assertIsString($serialized);

        $unserialized = $serializer->unserialize($serialized);
        $this->assertSame($data, $unserialized);
    }
}
