<?php

namespace Matchory\ResponseCache\Tests;

use InvalidArgumentException;
use Matchory\ResponseCache\Http\Middleware\BypassCache;
use Matchory\ResponseCache\Http\Middleware\CacheResponse;
use Matchory\ResponseCache\ResponseCacheProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @throws InvalidArgumentException
     */
    protected function defineRoutes($router): void
    {
        $router
            ->get('/uncached', function () {
                static $i = 0;

                return ++$i;
            })
            ->name('uncached');
        $router
            ->get('/cached', function () {
                static $i = 0;

                return ++$i;
            })
            ->middleware(CacheResponse::class)
            ->name('cached');
        $router
            ->get('/bypass', function () {
                static $i = 0;

                return ++$i;
            })
            ->middleware(CacheResponse::class)
            ->middleware(BypassCache::class)
            ->name('bypass');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ResponseCacheProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('response-cache.store', 'array');
    }
}
