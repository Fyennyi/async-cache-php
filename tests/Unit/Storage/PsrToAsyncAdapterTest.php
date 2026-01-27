<?php

namespace Tests\Unit\Storage;

use Fyennyi\AsyncCache\Storage\PsrToAsyncAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

class PsrToAsyncAdapterTest extends TestCase
{
    private MockObject|CacheInterface $psr;
    private PsrToAsyncAdapter $adapter;

    protected function setUp() : void
    {
        $this->psr = $this->createMock(CacheInterface::class);
        $this->adapter = new PsrToAsyncAdapter($this->psr);
    }

    public function testGet() : void
    {
        $this->psr->expects($this->once())->method('get')->with('k')->willReturn('v');
        $this->assertSame('v', $this->adapter->get('k')->wait());
    }

    public function testGetMultiple() : void
    {
        $this->psr->expects($this->once())->method('getMultiple')->with(['k'])->willReturn(['k' => 'v']);
        $this->assertSame(['k' => 'v'], $this->adapter->getMultiple(['k'])->wait());
    }

    public function testSet() : void
    {
        $this->psr->expects($this->once())->method('set')->with('k', 'v', 10)->willReturn(true);
        $this->assertTrue($this->adapter->set('k', 'v', 10)->wait());
    }

    public function testDelete() : void
    {
        $this->psr->expects($this->once())->method('delete')->with('k')->willReturn(true);
        $this->assertTrue($this->adapter->delete('k')->wait());
    }
    
    public function testClear() : void
    {
        $this->psr->expects($this->once())->method('clear')->willReturn(true);
        $this->assertTrue($this->adapter->clear()->wait());
    }
}
