<?php

namespace Fyennyi\AsyncCache\Lock;

/**
 * Interface for distributed locking support
 */
interface LockInterface
{
    /**
     * Acquires the lock
     * 
     * @param string $key The key to lock
     * @param float $ttl The time-to-live of the lock in seconds
     * @param bool $blocking Whether to wait for the lock to become available
     * @return bool True if lock acquired, false otherwise
     */
    public function acquire(string $key, float $ttl = 30.0, bool $blocking = false): bool;

    /**
     * Releases the lock
     * 
     * @param string $key The key to unlock
     */
    public function release(string $key): void;
}
