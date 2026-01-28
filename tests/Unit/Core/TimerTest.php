<?php

namespace Tests\Unit\Core;

use Fyennyi\AsyncCache\Core\Timer;
use Fyennyi\AsyncCache\Core\Future;
use PHPUnit\Framework\TestCase;

class TimerTest extends TestCase
{
    public function testDelay() : void
    {
        $start = microtime(true);
        $future = Timer::delay(0.01);
        $this->assertInstanceOf(Future::class, $future);
        $future->wait();
        $end = microtime(true);
        
        $this->assertGreaterThanOrEqual(0.01, $end - $start);
    }
}