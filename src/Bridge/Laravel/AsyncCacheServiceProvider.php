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

namespace Fyennyi\AsyncCache\Bridge\Laravel;

use Fyennyi\AsyncCache\AsyncCacheManager;
use Illuminate\Support\ServiceProvider;

/**
 * Service Provider for Laravel integration
 */
class AsyncCacheServiceProvider extends ServiceProvider
{
    /**
     * Register services in the container
     *
     * @return void
     */
    public function register() : void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/async-cache.php', 'async-cache');

        $this->app->singleton(AsyncCacheManager::class, function ($app) {
            $config = $app['config']['async-cache'];
            return AsyncCacheBuilder::create($app[$config['adapter']])
                ->withLogger($app['log'])
                ->build();
        });
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot() : void
    {
        $this->publishes([
            __DIR__ . '/../../../config/async-cache.php' => config_path('async-cache.php'),
        ], 'config');
    }
}
