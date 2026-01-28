<?php

namespace Tests\Unit\Storage;

use Fyennyi\AsyncCache\Storage\AsyncCacheAdapterInterface;
use Fyennyi\AsyncCache\Storage\ChainCacheAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use function React\Async\await;

class ChainCacheAdapterTest extends TestCase
{
    private MockObject|AsyncCacheAdapterInterface $l1;
    private MockObject|AsyncCacheAdapterInterface $l2;
    private ChainCacheAdapter $adapter;

    protected function setUp() : void
    {
        $this->l1 = $this->createMock(AsyncCacheAdapterInterface::class);
        $this->l2 = $this->createMock(AsyncCacheAdapterInterface::class);
        $this->adapter = new ChainCacheAdapter([$this->l1, $this->l2]);
    }

    public function testGetReturnsFromFirstLayer() : void
    {
        $d = new Deferred();
        $d->resolve('val1');
        $this->l1->method('get')->with('key')->willReturn($d->promise());
        $this->l2->expects($this->never())->method('get');

        $this->assertSame('val1', await($this->adapter->get('key')));
    }

    public function testGetFallsBackAndBackfills() : void
    {
        // L1 miss
        $d1 = new Deferred();
        $d1->resolve(null);
        $this->l1->method('get')->with('key')->willReturn($d1->promise());

        // L2 hit
        $d2 = new Deferred();
        $d2->resolve('val2');
        $this->l2->method('get')->with('key')->willReturn($d2->promise());

        // Expect L1 to be backfilled
        $this->l1->expects($this->once())->method('set')->with('key', 'val2');

        $this->assertSame('val2', await($this->adapter->get('key')));
    }

    public function testSetWritesToAllLayers() : void
    {
        $d1 = new Deferred();
        $d1->resolve(true);
        $this->l1->expects($this->once())->method('set')->with('k', 'v', 10)->willReturn($d1->promise());

        $d2 = new Deferred();
        $d2->resolve(true);
        $this->l2->expects($this->once())->method('set')->with('k', 'v', 10)->willReturn($d2->promise());

        $this->assertTrue(await($this->adapter->set('k', 'v', 10)));
    }

    public function testDeleteDeletesFromAllLayers() : void
    {
        $d1 = new Deferred();
        $d1->resolve(true);
        $this->l1->expects($this->once())->method('delete')->with('k')->willReturn($d1->promise());

        $d2 = new Deferred();
        $d2->resolve(true);
        $this->l2->expects($this->once())->method('delete')->with('k')->willReturn($d2->promise());

        $this->assertTrue(await($this->adapter->delete('k')));
    }
}
