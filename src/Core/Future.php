<?php

namespace Fyennyi\AsyncCache\Core;

use GuzzleHttp\Promise\Promise as GuzzlePromise;
use GuzzleHttp\Promise\PromiseInterface as GuzzlePromiseInterface;
use React\Promise\PromiseInterface as ReactPromiseInterface;
use React\Promise\Deferred as ReactDeferred;

/**
 * The core Future object of AsyncCache.
 */
class Future
{
    private array $handlers = [];
    private mixed $result = null;
    private bool $resolved = false;
    private bool $rejected = false;

    public function __construct(private $waitFn = null) {}

    public function then(callable $onFulfilled = null, callable $onRejected = null): self
    {
        $next = new self($this->waitFn);
        $handler = function () use ($next, $onFulfilled, $onRejected) {
            try {
                if ($this->rejected) {
                    if ($onRejected) {
                        $res = $onRejected($this->result);
                        $next->resolve($res);
                    } else {
                        $next->reject($this->result);
                    }
                } else {
                    if ($onFulfilled) {
                        $res = $onFulfilled($this->result);
                        $next->resolve($res);
                    } else {
                        $next->resolve($this->result);
                    }
                }
            } catch (\Throwable $e) {
                $next->reject($e);
            }
        };

        if ($this->resolved || $this->rejected) {
            $handler();
        } else {
            $this->handlers[] = $handler;
        }

        return $next;
    }

    public function resolve(mixed $value): void
    {
        if ($this->resolved || $this->rejected) return;
        $this->result = $value;
        $this->resolved = true;
        $this->fire();
    }

    public function reject(mixed $reason): void
    {
        if ($this->resolved || $this->rejected) return;
        $this->result = $reason;
        $this->rejected = true;
        $this->fire();
    }

    public function wait()
    {
        if (!$this->resolved && !$this->rejected && $this->waitFn) {
            ($this->waitFn)();
        }
        return $this->result;
    }

    /**
     * Converts to Guzzle Promise
     */
    public function toGuzzle(): GuzzlePromiseInterface
    {
        $guzzle = new GuzzlePromise(fn() => $this->wait());
        $this->then(
            fn($v) => $guzzle->resolve($v),
            fn($r) => $guzzle->reject($r)
        );
        return $guzzle;
    }

    /**
     * Converts to ReactPHP Promise
     */
    public function toReact(): ReactPromiseInterface
    {
        $deferred = new ReactDeferred();
        $this->then(
            fn($v) => $deferred->resolve($v),
            fn($r) => $deferred->reject($r)
        );
        return $deferred->promise();
    }

    private function fire(): void
    {
        foreach ($this->handlers as $handler) { $handler(); }
        $this->handlers = [];
    }
}