<?php

namespace Fyennyi\AsyncCache\Runtime;

/**
 * Automatically detects and creates the best runtime for the current environment
 */
class RuntimeFactory
{
    public static function create(): RuntimeInterface
    {
        if (ReactRuntime::isSupported()) {
            return new ReactRuntime();
        }

        // Future: add FiberRuntime::isSupported() here

        return new NativeRuntime();
    }
}
