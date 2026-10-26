<?php

namespace Matchory\ResponseCache\Tests;

use InvalidArgumentException;
use Matchory\ResponseCache\Http\Middleware\BypassCache;
use Matchory\ResponseCache\Facades\ResponseCache;
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
        $router
            ->get('/tagged/{item}', function () {
                static $i = 0;

                return ++$i;
            })
            ->middleware(CacheResponse::class . ':items.{item},filter.{filter}')
            ->name('tagged');
        $router
            ->get('/users/{user}', function () {
                static $i = 0;

                return ++$i;
            })
            ->middleware(CacheResponse::class . ':60,users,user:{user}')
            ->name('users.show');
        $router
            ->get('/racing', function () {
                static $i = 0;

                // Simulates a write that flushes while the response is built
                if (++$i === 1) {
                    ResponseCache::flush(['race']);
                }

                return $i;
            })
            ->middleware(CacheResponse::class . ':race')
            ->name('racing');
        $router
            ->post('/cached-post', function () {
                static $i = 0;

                return ++$i;
            })
            ->middleware(CacheResponse::class)
            ->name('cached-post');
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
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
    }
}
