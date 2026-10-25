<?php

namespace Matchory\ResponseCache\Tests\Feature;

use Matchory\ResponseCache\Facades\ResponseCache;

use function beforeEach;
use function it;
use function test;

beforeEach(function () {
    $this->app['cache']->flush();
});

it('resolves route parameters in tags', function () {
    test()->get('/tagged/1')->assertContent('1');
    test()->get('/tagged/2')->assertContent('2');

    ResponseCache::flush(['items.2']);

    test()->get('/tagged/1')->assertContent('1');
    test()->get('/tagged/2')->assertContent('3');
});

it('resolves query parameters in tags', function () {
    test()->get('/tagged/1?filter=a')->assertContent('1');
    test()->get('/tagged/1?filter=b')->assertContent('2');

    ResponseCache::flush(['filter.b']);

    test()->get('/tagged/1?filter=a')->assertContent('1');
    test()->get('/tagged/1?filter=b')->assertContent('3');
});
