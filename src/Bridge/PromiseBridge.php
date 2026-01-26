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

        if ($promise instanceof \GuzzleHttp\Promise\PromiseInterface) {
            $deferred = new \React\Promise\Deferred();
            $promise->then(
                fn($value) => $deferred->resolve($value),
                fn($reason) => $deferred->reject($reason)
            );
            // Run queue to trigger the then() above if the promise is already settled
            \GuzzleHttp\Promise\Utils::queue()->run();
            return $deferred->promise();
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

                if (class_exists('React\EventLoop\Loop')) {
                    // Drive the loop once to allow progress
                    \React\EventLoop\Loop::get()->futureTick(fn() => null);
                    \React\EventLoop\Loop::get()->run();
                } else {
                    break;
                }
            }
        });
        
        if ($promise instanceof ReactPromiseInterface) {
            $promise->then(
                function($value) use ($guzzle) {
                    if ($guzzle->getState() === GuzzlePromiseInterface::PENDING) {
                        $guzzle->resolve($value);
                        // CRITICAL: Process Guzzle's task queue immediately to trigger 'then' handlers
                        \GuzzleHttp\Promise\Utils::queue()->run();
                    }
                },
                function($reason) use ($guzzle) {
                    if ($guzzle->getState() === GuzzlePromiseInterface::PENDING) {
                        $guzzle->reject($reason);
                        // CRITICAL: Process Guzzle's task queue immediately to trigger 'then' handlers
                        \GuzzleHttp\Promise\Utils::queue()->run();
                    }
                }
            );
        } else {
            $guzzle->resolve($promise);
            \GuzzleHttp\Promise\Utils::queue()->run();
        }

        return $guzzle;
    }
}
