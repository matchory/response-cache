<?php

namespace Matchory\ResponseCache\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Matchory\ResponseCache\Repository;

use function it;
use function test;

it('refuses the failover store', function () {
    $this->app['config']->set('cache.stores.failover', [
        'driver' => 'failover',
        'stores' => ['array'],
    ]);
    $this->app['config']->set('response-cache.store', 'failover');

    $this->app->make(Repository::class);
})->throws(InvalidArgumentException::class, 'failover');

it('refuses a tag TTL below one second', function (mixed $ttl) {
    $this->app['config']->set('response-cache.tag_ttl', $ttl);

    $this->app->make(Repository::class);
})->with([0, '', null])->throws(InvalidArgumentException::class, 'tag_ttl');

it('caches responses when the store may not unserialize objects', function () {
    // The default in new Laravel 13 applications
    $this->app['config']->set('cache.serializable_classes', false);
    $this->app['config']->set('response-cache.store', 'file');

    try {
        test()->get('/cached')->assertContent('1');
        test()->get('/cached')->assertContent('1')->assertHeader('Response-Cache-Status', 'hit');
    } finally {
        Cache::store('file')->flush();
    }
});
