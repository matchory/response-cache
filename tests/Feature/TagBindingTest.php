<?php

namespace Matchory\ResponseCache\Tests\Feature;

use Matchory\ResponseCache\Facades\ResponseCache;

use function beforeEach;
use function it;
use function test;

beforeEach(function () {
    $this->app['cache']->flush();
});

it('resolves bound models with integer keys in tags', function () {
    test()->get('/models/1')->assertContent('1');
    test()->get('/models/2')->assertContent('2');

    ResponseCache::flush(['models.2']);

    test()->get('/models/1')->assertContent('1');
    test()->get('/models/2')->assertContent('3');
});
