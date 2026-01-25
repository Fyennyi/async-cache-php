<?php

namespace Fyennyi\AsyncCache\Enum;

/**
 * Strategy for handling cache misses and expiration
 */
enum CacheStrategy: string
{
    /**
     * Default behavior: wait for fresh data if expired
     */
    case Strict = 'strict';

    /**
     * Stale-While-Revalidate: return stale data and refresh in background
     */
    case Background = 'background';

    /**
     * Skip cache and force fetch fresh data
     */
    case ForceRefresh = 'force_refresh';
}
