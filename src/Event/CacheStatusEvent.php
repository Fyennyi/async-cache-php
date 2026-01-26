<?php

namespace Fyennyi\AsyncCache\Event;

use Fyennyi\AsyncCache\Enum\CacheStatus;

/**
 * Event dispatched for every cache resolution attempt to support metrics and telemetry
 */
class CacheStatusEvent extends AsyncCacheEvent
{
    public function __construct(
        string $key,
        public readonly CacheStatus $status,
        public readonly float $latency = 0.0,
        public readonly array $tags = []
    ) {
        parent::__construct($key, microtime(true));
    }
}
