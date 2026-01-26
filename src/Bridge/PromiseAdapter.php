<?php

namespace Fyennyi\AsyncCache\Bridge;

use Fyennyi\AsyncCache\Core\Deferred;
use Fyennyi\AsyncCache\Core\Future;
use GuzzleHttp\Promise\Promise as GuzzlePromise;
use React\Promise\Deferred as ReactDeferred;
use React\Promise\PromiseInterface as ReactPromiseInterface;

/**
 * Converts internal Future placeholders to industry-standard Promise objects
 */
class PromiseAdapter
{
    /**
     * Converts a native Future to a Guzzle Promise
     *
     * @param  Future  $future  The internal future to convert
     * @return \GuzzleHttp\Promise\PromiseInterface Guzzle promise resolving with the future's result
     */
    public static function toGuzzle(Future $future) : \GuzzleHttp\Promise\PromiseInterface
    {
        $guzzle = new GuzzlePromise();
        $future->onResolve(
            fn($v) => $guzzle->resolve($v),
            fn($r) => $guzzle->reject($r)
        );
        return $guzzle;
    }

    /**
     * Converts a native Future to a ReactPHP Promise
     *
     * @param  Future  $future  The internal future to convert
     * @return ReactPromiseInterface React promise resolving with the future's result
     */
    public static function toReact(Future $future) : ReactPromiseInterface
    {
        $deferred = new ReactDeferred();
        $future->onResolve(
            fn($v) => $deferred->resolve($v),
            fn($r) => $deferred->reject($r)
        );
        return $deferred->promise();
    }

    /**
     * Converts a ReactPHP Promise to a native Future
     *
     * @param  ReactPromiseInterface  $promise  The external promise to wrap
     * @return Future                           Internal future tracking the promise state
     */
    public static function toFuture(ReactPromiseInterface $promise) : Future
    {
        $deferred = new Deferred();
        $promise->then(
            fn($v) => $deferred->resolve($v),
            fn($r) => $deferred->reject($r)
        );
        return $deferred->future();
    }
}
