<?php

namespace Fyennyi\AsyncCache\Enum;

/**
 * Supported rate limiter implementations
 */
enum RateLimiterType: string
{
    case Symfony = 'symfony';
    case InMemory = 'in_memory';
    case Auto = 'auto';
}
