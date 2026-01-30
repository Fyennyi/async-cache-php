<?php

namespace Tests\Unit\Storage;

use Fyennyi\AsyncCache\Storage\PsrToAsyncAdapter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use function Clue\React\Block\await;

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
        $this->assertSame('v', await($this->adapter->get('k')));
    }

    public function testGetMultiple() : void
    {
        $this->psr->expects($this->once())->method('getMultiple')->with(['k'])->willReturn(['k' => 'v']);
        $this->assertSame(['k' => 'v'], await($this->adapter->getMultiple(['k'])));
    }

    public function testSet() : void
    {
        $this->psr->expects($this->once())->method('set')->with('k', 'v', 10)->willReturn(true);
        $this->assertTrue(await($this->adapter->set('k', 'v', 10)));
    }

    public function testDelete() : void
    {
        $this->psr->expects($this->once())->method('delete')->with('k')->willReturn(true);
        $this->assertTrue(await($this->adapter->delete('k')));
    }

    public function testClear() : void
    {
        $this->psr->expects($this->once())->method('clear')->willReturn(true);
        $this->assertTrue(await($this->adapter->clear()));
    }

    public function testMethodsCatchException() : void
    {
        $this->psr->method('get')->willThrowException(new \Exception('PSR error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('PSR error');

        await($this->adapter->get('k'));
    }

    public function testGetMultipleCatchException() : void
    {
        $this->psr->method('getMultiple')->willThrowException(new \Exception('PSR error'));
        $this->expectException(\Exception::class);
        await($this->adapter->getMultiple(['k']));
    }

    public function testSetCatchException() : void
    {
        $this->psr->method('set')->willThrowException(new \Exception('PSR error'));
        $this->expectException(\Exception::class);
        await($this->adapter->set('k', 'v'));
    }

    public function testDeleteCatchException() : void
    {
        $this->psr->method('delete')->willThrowException(new \Exception('PSR error'));
        $this->expectException(\Exception::class);
        await($this->adapter->delete('k'));
    }

    public function testClearCatchException() : void
    {
        $this->psr->method('clear')->willThrowException(new \Exception('PSR error'));
        $this->expectException(\Exception::class);
        await($this->adapter->clear());
    }
}
