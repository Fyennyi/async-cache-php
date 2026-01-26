<?php

namespace Fyennyi\AsyncCache\Bridge\Symfony\DependencyInjection;

use Fyennyi\AsyncCache\AsyncCacheManager;
use Fyennyi\AsyncCache\Lock\LockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Dependency injection extension for Symfony
 */
class AsyncCacheExtension extends Extension
{
    /**
     * Loads the configuration and registers the AsyncCacheManager service
     *
     * @param  array             $configs    The configuration array
     * @param  ContainerBuilder  $container  The container builder
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container) : void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $definition = new Definition(AsyncCacheManager::class);

        $definition->setArguments([
            '$cache_adapter' => new Reference(CacheInterface::class),
            '$rate_limiter' => null, // Expects explicit configuration if needed
            '$logger' => new Reference(LoggerInterface::class),
            '$lock_provider' => new Reference(LockInterface::class),
            '$middlewares' => [],
            '$dispatcher' => new Reference(EventDispatcherInterface::class)
        ]);

        $definition->setPublic(true);
        $container->setDefinition(AsyncCacheManager::class, $definition);
    }
}
