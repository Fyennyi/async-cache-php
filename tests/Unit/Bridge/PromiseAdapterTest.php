<?php

namespace Tests\Unit\Bridge;

use Fyennyi\AsyncCache\Bridge\PromiseAdapter;
use Fyennyi\AsyncCache\Core\Deferred;
use GuzzleHttp\Promise\Promise as GuzzlePromise;
use GuzzleHttp\Promise\Utils;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred as ReactDeferred;

class PromiseAdapterTest extends TestCase
{
    public function testToGuzzle() : void
    {
        $d = new Deferred();
        $guzzle = PromiseAdapter::toGuzzle($d->future());
        
        $this->assertInstanceOf(GuzzlePromise::class, $guzzle);
        $this->assertSame('pending', $guzzle->getState());
        
        $d->resolve('ok');
        // We might need to run queue here too if GuzzlePromise uses it internally for callbacks
        Utils::queue()->run();
        
        $this->assertSame('fulfilled', $guzzle->getState());
        $guzzle->then(fn($v) => $this->assertSame('ok', $v));
    }

    public function testToReact() : void
    {
        $d = new Deferred();
        $react = PromiseAdapter::toReact($d->future());
        
        $result = null;
        $react->then(function($v) use (&$result) { $result = $v; });
        
        $d->resolve('ok');
        $this->assertSame('ok', $result);
    }
    
    public function testFromGuzzle() : void
    {
        $guzzle = new GuzzlePromise();
        $future = PromiseAdapter::toFuture($guzzle);
        
        $guzzle->resolve('ok');
        Utils::queue()->run(); // Process Guzzle callbacks
        
        $this->assertSame('ok', $future->wait());
    }

    public function testFromReact() : void
    {
        $reactDeferred = new ReactDeferred();
        $future = PromiseAdapter::toFuture($reactDeferred->promise());
        
        $reactDeferred->resolve('ok');
        $this->assertSame('ok', $future->wait());
    }
}
