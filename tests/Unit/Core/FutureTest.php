<?php

namespace Tests\Unit\Core;

use Fyennyi\AsyncCache\Core\Deferred;
use Fyennyi\AsyncCache\Core\Future;
use PHPUnit\Framework\TestCase;

class FutureTest extends TestCase
{
    public function testResolve() : void
    {
        $deferred = new Deferred();
        $future = $deferred->future();

        $resolvedVal = null;
        $future->onResolve(function($v) use (&$resolvedVal) {
            $resolvedVal = $v;
        });

        $deferred->resolve('success');
        
        $this->assertTrue($future->isReady());
        $this->assertFalse($future->isFailed());
        $this->assertSame('success', $resolvedVal);
        $this->assertSame('success', $future->getResult());
        $this->assertSame('success', $future->wait());
    }

    public function testReject() : void
    {
        $deferred = new Deferred();
        $future = $deferred->future();

        $rejectedReason = null;
        $future->onResolve(null, function($r) use (&$rejectedReason) {
            $rejectedReason = $r;
        });

        $exception = new \Exception('fail');
        $deferred->reject($exception);

        $this->assertTrue($future->isReady());
        $this->assertTrue($future->isFailed());
        $this->assertSame($exception, $rejectedReason);
        $this->assertSame($exception, $future->getResult());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('fail');
        $future->wait();
    }

    public function testAlreadyResolvedListeners() : void
    {
        $deferred = new Deferred();
        $future = $deferred->future();
        $deferred->resolve('early');

        $val = null;
        $future->onResolve(function($v) use (&$val) {
            $val = $v;
        });

        $this->assertSame('early', $val);
    }

    public function testAlreadyRejectedListeners() : void
    {
        $deferred = new Deferred();
        $future = $deferred->future();
        $deferred->reject(new \Exception('early'));

        $reason = null;
        $future->onResolve(null, function($r) use (&$reason) {
            $reason = $r;
        });

        $this->assertInstanceOf(\Exception::class, $reason);
        $this->assertSame('early', $reason->getMessage());
    }

    public function testRejectWithScalar() : void
    {
        $deferred = new Deferred();
        $deferred->reject("string error");
        $future = $deferred->future();

        try {
            $future->wait();
            $this->fail("Should have thrown");
        } catch (\RuntimeException $e) {
            $this->assertSame("string error", $e->getMessage());
        }
    }
}
