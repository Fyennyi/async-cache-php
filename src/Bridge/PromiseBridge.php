<?php

namespace Fyennyi\AsyncCache\Bridge;

use GuzzleHttp\Promise\Promise as GuzzlePromise;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use React\Promise\PromiseInterface as ReactPromiseInterface;
use function React\Promise\resolve;

/**
 * Lightweight bridge for promise interoperability at the library boundaries
 */
class PromiseBridge
{
    /**
     * Converts any thenable to a ReactPHP Promise
     */
    public static function toReact(mixed $promise): ReactPromiseInterface
    {
        if ($promise instanceof ReactPromiseInterface) {
            return $promise;
        }

        return resolve($promise);
    }

    /**
     * Converts any thenable to a Guzzle Promise
     */
    public static function toGuzzle(mixed $promise): GuzzlePromiseInterface
    {
        if ($promise instanceof GuzzlePromiseInterface) {
            return $promise;
        }

        $guzzle = new GuzzlePromise();
        
        if ($promise instanceof ReactPromiseInterface) {
            $promise->then(
                fn($value) => $guzzle->resolve($value),
                fn($reason) => $guzzle->reject($reason)
            );
        } else {
            $guzzle->resolve($promise);
        }

        return $guzzle;
    }
}
