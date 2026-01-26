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

        /** @var GuzzlePromise $guzzle */
        $guzzle = new GuzzlePromise(function() use (&$guzzle) {
            while ($guzzle->getState() === GuzzlePromiseInterface::PENDING) {
                \GuzzleHttp\Promise\Utils::queue()->run();
                
                if ($guzzle->getState() !== GuzzlePromiseInterface::PENDING) {
                    break;
                }

                // If we are in a ReactPHP environment, drive the loop to process timers/events
                if (class_exists('React\EventLoop\Loop')) {
                    // This will run the loop until there are no more active timers or events.
                    // Since our AsyncLockMiddleware uses delay(), the loop will run 
                    // until that delay expires and resolves the promise.
                    \React\EventLoop\Loop::get()->run();
                } else {
                    // Safety break if no way to progress
                    break;
                }
            }
        });
        
        if ($promise instanceof ReactPromiseInterface) {
            $promise->then(
                function($value) use ($guzzle) {
                    if ($guzzle->getState() === GuzzlePromiseInterface::PENDING) {
                        $guzzle->resolve($value);
                    }
                },
                function($reason) use ($guzzle) {
                    if ($guzzle->getState() === GuzzlePromiseInterface::PENDING) {
                        $guzzle->reject($reason);
                    }
                }
            );
        } else {
            $guzzle->resolve($promise);
        }

        return $guzzle;
    }
}
