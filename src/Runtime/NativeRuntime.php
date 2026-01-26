<?php

namespace Fyennyi\AsyncCache\Runtime;

use React\Promise\PromiseInterface;
use function React\Promise\resolve;

/**
 * Fallback runtime for standard synchronous PHP
 */
class NativeRuntime implements RuntimeInterface
{
    public function delay(float $seconds): PromiseInterface
    {
        usleep((int)($seconds * 1000000));
        return resolve(null);
    }

    public function resolve(mixed $value): PromiseInterface
    {
        return resolve($value);
    }

    public static function isSupported(): bool
    {
        return true; // Always supported
    }
}
