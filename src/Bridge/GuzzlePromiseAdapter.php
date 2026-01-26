<?php

namespace Fyennyi\AsyncCache\Bridge;

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use React\Promise\PromiseInterface as ReactPromiseInterface;

/**
 * Adapter to convert ReactPHP promises to Guzzle promises and synchronize event loops
 */
class GuzzlePromiseAdapter
{
    /**
     * Registers a periodic timer in ReactPHP loop to process Guzzle's task queue.
     * Essential for Guzzle promises to resolve in an async environment.
     */
    public static function registerLoop(): void
    {
        if (class_exists('React\EventLoop\Loop')) {
            $runQueue = function () use (&$runQueue) {
                Utils::queue()->run();
                \React\EventLoop\Loop::futureTick($runQueue);
            };
            
            \React\EventLoop\Loop::futureTick($runQueue);
        }
    }

    public static function wrap(mixed $promise): GuzzlePromiseInterface
    {
        if ($promise instanceof GuzzlePromiseInterface) {
            return $promise;
        }

        if ($promise instanceof ReactPromiseInterface) {
            $guzzlePromise = new Promise();
            $promise->then(
                function ($value) use ($guzzlePromise) {
                    $guzzlePromise->resolve($value);
                },
                function ($reason) use ($guzzlePromise) {
                    $guzzlePromise->reject($reason);
                }
            );
            return $guzzlePromise;
        }

        return \GuzzleHttp\Promise\Create::promiseFor($promise);
    }
}
