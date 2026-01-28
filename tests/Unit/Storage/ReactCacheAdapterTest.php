<?php

namespace Tests\Unit\Storage;

use Fyennyi\AsyncCache\Storage\ReactCacheAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\Cache\CacheInterface;
use React\Promise\Promise;
use function React\Async\await;

class ReactCacheAdapterTest extends TestCase
{
    private MockObject|CacheInterface $react;
    private ReactCacheAdapter $adapter;

    protected function setUp() : void
    {
        $this->react = $this->createMock(CacheInterface::class);
        $this->adapter = new ReactCacheAdapter($this->react);
    }

    public function testGet() : void
    {
        $this->react->expects($this->once())->method('get')->with('k')->willReturn(new Promise(function ($resolve) {
            $resolve('v');
        }));
        $this->assertSame('v', await($this->adapter->get('k')));
    }

    public function testSet() : void
    {
        $this->react->expects($this->once())->method('set')->with('k', 'v', 10)->willReturn(new Promise(function ($resolve) {
            $resolve(true);
        }));
        $this->assertTrue(await($this->adapter->set('k', 'v', 10)));
    }

    public function testDelete() : void
    {
        $this->react->expects($this->once())->method('delete')->with('k')->willReturn(new Promise(function ($resolve) {
            $resolve(true);
        }));
        $this->assertTrue(await($this->adapter->delete('k')));
    }
}
