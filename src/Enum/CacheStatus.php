<?php

namespace Fyennyi\AsyncCache\Enum;

/**
 * Internal cache operation status
 */
enum CacheStatus: string
{
    case Hit = 'hit';
    case Miss = 'miss';
    case Stale = 'stale';
    case RateLimited = 'rate_limited';
    case XFetch = 'x_fetch';
    case Bypass = 'bypass';
}
