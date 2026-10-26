<?php

namespace Matchory\ResponseCache\Tests\Feature;

use InvalidArgumentException;
use Matchory\ResponseCache\Repository;

use function it;

it('refuses the failover store', function () {
    $this->app['config']->set('cache.stores.failover', [
        'driver' => 'failover',
        'stores' => ['array'],
    ]);
    $this->app['config']->set('response-cache.store', 'failover');

    $this->app->make(Repository::class);
})->throws(InvalidArgumentException::class, 'failover');
