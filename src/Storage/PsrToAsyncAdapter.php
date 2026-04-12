<?php
namespace Fyennyi\AsyncCache\Storage;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use React\Promise\PromiseInterface;

class PsrToAsyncAdapter implements AsyncCacheAdapterInterface
{
    public function __construct(private PsrCacheInterface $psr_cache) {}
    public function get(string $key) : PromiseInterface {
        try { return \React\Promise\resolve($this->psr_cache->get($key)); }
        catch (\Throwable $e) { return \React\Promise\reject($e); }
    }
    public function getMultiple(iterable $keys) : PromiseInterface {
        try { 
            $values = $this->psr_cache->getMultiple($keys);
            if ($values instanceof \Traversable) { $values = iterator_to_array($values); }
            
            $result = [];
            foreach ($values as $k => $v) {
                // Якщо Symfony повернула нам свій внутрішній "запакований" об'єкт
                if (is_object($v) && property_exists($v, 'value')) {
                    $result[$k] = $v->value;
                } else {
                    $result[$k] = $v;
                }
            }
            return \React\Promise\resolve($result); 
        }
        catch (\Throwable $e) { return \React\Promise\reject($e); }
    }
    public function set(string $key, mixed $value, ?int $ttl = null) : PromiseInterface {
        try { return \React\Promise\resolve($this->psr_cache->set($key, $value, $ttl)); }
        catch (\Throwable $e) { return \React\Promise\reject($e); }
    }
    public function delete(string $key) : PromiseInterface {
        try { return \React\Promise\resolve($this->psr_cache->delete($key)); }
        catch (\Throwable $e) { return \React\Promise\reject($e); }
    }
    public function clear() : PromiseInterface {
        try { return \React\Promise\resolve($this->psr_cache->clear()); }
        catch (\Throwable $e) { return \React\Promise\reject($e); }
    }
}
