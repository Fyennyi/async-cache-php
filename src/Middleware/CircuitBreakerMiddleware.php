<?php

/*
 *
 *     _                          ____           _            ____  _   _ ____
 *    / \   ___ _   _ _ __   ___ / ___|__ _  ___| |__   ___  |  _ \| | | |  _ \
 *   / _ \ / __| | | | '_ \ / __| |   / _` |/ __| '_ \ / _ \ | |_) | |_| | |_) |
 *  / ___ \\__ \ |_| | | | | (__| |__| (_| | (__| | | |  __/ |  __/|  _  |  __/
 * /_/   \_\___/\__, |_| |_|\___|\____\__,_|\___|_| |_|\___| |_|   |_| |_|_|
 *              |___/
 *
 * This program is free software: you can redistribute and/or modify
 * it under the terms of the CSSM Unlimited License v2.0.
 *
 * This license permits unlimited use, modification, and distribution
 * for any purpose while maintaining authorship attribution.
 *
 * The software is provided "as is" without warranty of any kind.
 *
 * @author Serhii Cherneha
 * @link https://chernega.eu.org/
 *
 *
 */

namespace Fyennyi\AsyncCache\Middleware;

use Fyennyi\AsyncCache\Core\CacheContext;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Middleware that prevents cascading failures by stopping requests to failing services.
 */
class CircuitBreakerMiddleware implements MiddlewareInterface
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';

    private LoggerInterface $logger;

    /**
     * @param CacheInterface       $storage           Storage for breaker state and failure counts
     * @param LockFactory          $lock_factory      Symfony Lock Factory for half-open probes
     * @param int                  $failure_threshold Number of failures before opening the circuit
     * @param int                  $retry_timeout     Timeout in seconds before moving to half-open state
     * @param string               $prefix            Cache key prefix for breaker state
     * @param LoggerInterface|null $logger            Logger for state changes
     */
    public function __construct(
        private CacheInterface $storage,
        private LockFactory $lock_factory,
        private int $failure_threshold = 5,
        private int $retry_timeout = 60,
        private string $prefix = 'cb:',
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Orchestrates circuit state transitions and request blocking.
     *
     * @template T
     *
     * @param  callable(CacheContext):PromiseInterface<T> $next Next handler in the chain
     * @return PromiseInterface<T>                        Result promise
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $lock_key = $this->prefix . 'lock:' . $context->key;
        $state_key = $this->prefix . 'state:' . $context->key;
        $failure_key = $this->prefix . 'fail:' . $context->key;
        $last_fail_key = $this->prefix . 'last_fail:' . $context->key;

        /** @var mixed $raw_failure_time */
        $raw_failure_time = $this->storage->get($last_fail_key, 0);
        $last_failure_time = is_numeric($raw_failure_time) ? (int) $raw_failure_time : 0;

        if ($last_failure_time > 0) {
            // Half-open check: allow a single probe request after timeout
            if ($context->clock->now()->getTimestamp() - $last_failure_time < $this->retry_timeout) {
                $this->logger->error('AsyncCache CIRCUIT_BREAKER: Open state, blocking request', ['key' => $context->key]);
                /** @var PromiseInterface<never> $reject */
                $reject = \React\Promise\reject(new \RuntimeException("Circuit Breaker is OPEN for key: {$context->key}"));

                return $reject;
            }

            // Half-open: try to acquire a probe lock
            $lock = $this->lock_factory->createLock($lock_key, 30.0);
            if (! $lock->acquire(false)) {
                $this->logger->debug('AsyncCache CIRCUIT_BREAKER: Half-open, but probe already in progress', ['key' => $context->key]);
                /** @var PromiseInterface<never> $reject */
                $reject = \React\Promise\reject(new \RuntimeException("Circuit Breaker is HALF-OPEN for key: {$context->key} (Probe in progress)"));

                return $reject;
            }

            $this->logger->info('AsyncCache CIRCUIT_BREAKER: Half-open, allowing probe request', ['key' => $context->key]);
        }

        /** @var PromiseInterface<T> $promise */
        $promise = $next($context);

        /** @var PromiseInterface<T> $result */
        $result = $promise->then(
            function ($data) use ($state_key, $failure_key, $last_fail_key, $context) {
                $this->onSuccess($state_key, $failure_key, $last_fail_key, $context->key);

                return $data;
            },
            function (\Throwable $reason) use ($state_key, $failure_key, $last_fail_key, $context) {
                $this->onFailure($state_key, $failure_key, $last_fail_key, $context);
                throw $reason;
            }
        );

        return $result;
    }

    /**
     * Handles successful request completion.
     */
    private function onSuccess(string $state_key, string $failure_key, string $last_fail_key, string $key) : void
    {
        $this->storage->set($state_key, self::STATE_CLOSED);
        $this->storage->set($failure_key, 0);
        $this->storage->delete($last_fail_key);
        $this->logger->info('AsyncCache CIRCUIT_BREAKER: Success, circuit closed', ['key' => $key]);
    }

    /**
     * Handles request failure.
     */
    private function onFailure(string $state_key, string $failure_key, string $last_fail_key, CacheContext $context) : void
    {
        /** @var mixed $val */
        $val = $this->storage->get($failure_key, 0);
        $failures = (is_numeric($val) ? (int) $val : 0) + 1;
        $this->storage->set($failure_key, $failures);

        if ($failures >= $this->failure_threshold) {
            $this->storage->set($state_key, self::STATE_OPEN);
            $this->storage->set($last_fail_key, $context->clock->now()->getTimestamp());
            $this->logger->critical('AsyncCache CIRCUIT_BREAKER: Failure threshold reached, opening circuit', [
                'key' => $context->key,
                'failures' => $failures
            ]);
        }
    }
}
