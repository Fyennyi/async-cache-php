<?php

namespace Fyennyi\AsyncCache\Lock;

class InMemoryLockAdapter implements LockInterface
{
    private array $locks = [];

    public function acquire(string $key, float $ttl = 30.0, bool $blocking = false): bool
    {
        if (isset($this->locks[$key]) && $this->locks[$key] > microtime(true)) {
            return false;
        }

        $this->locks[$key] = microtime(true) + $ttl;
        return true;
    }

    public function release(string $key): void
    {
        unset($this->locks[$key]);
    }
}
