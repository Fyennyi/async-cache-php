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
use Fyennyi\AsyncCache\Enum\CacheStatus;
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Fyennyi\AsyncCache\Event\RateLimitExceededEvent;
use Fyennyi\AsyncCache\Exception\RateLimitException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use function React\Promise\resolve;

/**
 * Enforces rate limiting for cache operations.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param RateLimiterFactoryInterface|null $limiter    The rate limiter factory
     * @param LoggerInterface                  $logger     Logging implementation
     * @param EventDispatcherInterface|null    $dispatcher Event dispatcher for telemetry
     */
    public function __construct(
        private ?RateLimiterFactoryInterface $limiter,
        private LoggerInterface $logger,
        private ?EventDispatcherInterface $dispatcher = null
    ) {}

    /**
     * @template T
     *
     * @param  CacheContext                               $context
     * @param  callable(CacheContext):PromiseInterface<T> $next
     * @return PromiseInterface<T>
     */
    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $rate_limit_key = $context->options->rate_limit_key;

        if (null === $this->limiter || null === $rate_limit_key) {
            return $next($context);
        }

        $this->logger->debug('AsyncCache RATELIMIT_CHECK: Checking rate limit', [
            'key' => $context->key,
            'rate_limit_key' => $rate_limit_key,
        ]);

        $limiter = $this->limiter->create($rate_limit_key);
        $rate_limit = $limiter->consume();

        if ($rate_limit->isAccepted()) {
            return $next($context);
        }

        $this->logger->warning('AsyncCache RATELIMIT_EXCEEDED: Rate limit exceeded', [
            'key' => $context->key,
            'rate_limit_key' => $rate_limit_key,
        ]);

        $now = (float) $context->clock->now()->format('U.u');
        $this->dispatcher?->dispatch(new RateLimitExceededEvent($context->key, $rate_limit_key, $now));
        $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::RateLimited, $context->getElapsedTime(), $context->options->tags, $now));

        if ($context->options->serve_stale_if_limited && null !== $context->stale_item) {
            $this->logger->debug('AsyncCache RATELIMIT_SERVE_STALE: Serving stale data due to rate limit', [
                'key' => $context->key,
            ]);

            /** @var T $data */
            $data = $context->stale_item->data;

            return resolve($data);
        }

        throw new RateLimitException($rate_limit_key);
    }
}
