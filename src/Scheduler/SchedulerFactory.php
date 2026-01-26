<?php

namespace Fyennyi\AsyncCache\Scheduler;

/**
 * Automatically detects and creates the best runtime for the current environment
 */
class SchedulerFactory
{
    public static function create(): SchedulerInterface
    {
        if (FiberScheduler::isSupported()) {
            return new FiberScheduler();
        }

        if (ReactScheduler::isSupported()) {
            return new ReactScheduler();
        }

        return new NativeScheduler();
    }
}
