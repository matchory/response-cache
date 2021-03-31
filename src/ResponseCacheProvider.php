<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache;

use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Matchory\ResponseCache\Commands\FlushCacheCommand;
use Matchory\ResponseCache\Contracts\CacheStrategy;
use Matchory\ResponseCache\Support\BaseStrategy;

use function config;

/**
 * Response Cache Provider
 *
 * @package Matchory\ResponseCache
 */
class ResponseCacheProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configure();
    }

    /**
     * Register any application services.
     *
     * @return void
     * @throws LogicException
     */
    public function register(): void
    {
        $this->registerRepository();
        $this->registerDefaultStrategy();
        $this->registerCache();
        $this->registerCommands();
    }

    /**
     * @throws LogicException
     */
    protected function registerRepository(): void
    {
        $this->app
            ->when(Repository::class)
            ->needs(CacheRepository::class)
            ->give(fn() => $this->app
                ->make(Factory::class)
                ->store(config('response-cache.store'))
            );

        $this->app->bind(Repository::class);
        $this->app->alias(
            Repository::class,
            'response-cache.repository'
        );
    }

    /**
     * @throws LogicException
     */
    protected function registerCache(): void
    {
        $this->app->singleton(ResponseCache::class);
        $this->app->alias(ResponseCache::class, 'response-cache');
    }

    /**
     * @throws LogicException
     */
    protected function registerDefaultStrategy(): void
    {
        $this->app->bind(
            CacheStrategy::class,
            BaseStrategy::class
        );
        $this->app->alias(
            CacheStrategy::class,
            'response-cache.strategy'
        );
    }

    protected function configure(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/response-cache.php',
            'response-cache'
        );

        $this->publishes([
            __DIR__ . '/../config/' => $this->app->configPath(),
        ], 'config');
    }

    protected function registerCommands(): void
    {
        if ( ! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            FlushCacheCommand::class,
        ]);
    }
}
