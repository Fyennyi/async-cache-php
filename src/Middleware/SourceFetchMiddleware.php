<?php
namespace Fyennyi\AsyncCache\Middleware;
use Fyennyi\AsyncCache\Core\CacheContext;
use Fyennyi\AsyncCache\Enum\CacheStatus;
use Fyennyi\AsyncCache\Event\CacheStatusEvent;
use Fyennyi\AsyncCache\Storage\CacheStorage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\Promise\PromiseInterface;

class SourceFetchMiddleware implements MiddlewareInterface
{
    public function __construct(private CacheStorage $storage, private LoggerInterface $logger, private ?EventDispatcherInterface $dispatcher = null) {}

    public function handle(CacheContext $context, callable $next) : PromiseInterface
    {
        $start = (float) $context->clock->now()->format('U.u');
        try {
            return $next($context)->then(function ($data) use ($context, $start) {
                $now = (float) $context->clock->now()->format('U.u');
                $generation_time = $now - $start;
                $this->dispatcher?->dispatch(new CacheStatusEvent($context->key, CacheStatus::Miss, $context->getElapsedTime(), $context->options->tags, $now));
                
                // ВАЖЛИВО: чекаємо завершення запису
                return $this->storage->set($context->key, $data, $context->options, $generation_time)
                    ->then(fn() => $data)
                    ->catch(fn() => $data);
            });
        } catch (\Throwable $e) {
            return \React\Promise\reject($e);
        }
    }
}
