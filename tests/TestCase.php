<?php

namespace Matchory\ResponseCache\Tests;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Routing\Middleware\SubstituteBindings;
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

        // Binds like an Eloquent model with an integer key
        $router->bind('model', fn(string $value) => new class ((int) $value) implements UrlRoutable {
            public function __construct(private readonly int $key) {}

            public function getRouteKey(): int
            {
                return $this->key;
            }

            public function getRouteKeyName(): string
            {
                return 'id';
            }

            public function resolveRouteBinding($value, $field = null): ?self
            {
                return null;
            }

            public function resolveChildRouteBinding($childType, $value, $field): ?self
            {
                return null;
            }
        });
        $router
            ->get('/models/{model}', function () {
                static $i = 0;

                return ++$i;
            })
            ->middleware([SubstituteBindings::class, CacheResponse::class . ':models.{model}'])
            ->name('models');
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
