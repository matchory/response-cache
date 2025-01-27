<?php

namespace Matchory\ResponseCache\Tests\Feature;

use function beforeEach;
use function it;
use function test;

beforeEach(function () {
    $this->app['cache']->flush();
});

it('does not cache responses without middleware', function () {
    test()->get('/uncached')->assertContent('1');
    test()->get('/uncached')->assertContent('2');
    test()->get('/uncached')->assertContent('3');
});

it('caches responses with middleware', function () {
    test()->get('/cached')->assertContent('1');
    test()->get('/cached')->assertContent('1');
    test()->get('/cached')->assertContent('1');
});

it('does not cache responses with bypass middleware', function () {
    test()->get('/bypass')->assertContent('1');
    test()->get('/bypass')->assertContent('2');
    test()->get('/bypass')->assertContent('3');
});

it('adds no timing headers if disabled', function () {
    $this->app['config']->set('response-cache.server_timing', false);

    test()->get('/uncached')->assertHeaderMissing('Server-Timing');
    test()->get('/uncached')->assertHeaderMissing('Server-Timing');
    test()->get('/cached')->assertHeaderMissing('Server-Timing');
    test()->get('/cached')->assertHeaderMissing('Server-Timing');
});

it('adds timing headers if enabled', function () {
    $this->app['config']->set('response-cache.server_timing', true);

    test()->get('/uncached')->assertHeaderMissing('Server-Timing');
    test()->get('/cached')->assertHeaderMissing('Server-Timing');
    test()->get('/cached')->assertHeader('Server-Timing');
});

it('adds cache status headers', function () {
    test()->get('/cached')->assertHeader('Response-Cache-Status', 'miss');
    test()->get('/cached')->assertHeader('Response-Cache-Status', 'hit');
    test()->get('/cached')->assertHeader('Response-Cache-Status', 'hit');
});

it('adds no cache status headers if disabled', function () {
    $this->app['config']->set('response-cache.cache_status_enabled', false);

    test()->get('/uncached')->assertHeaderMissing('Response-Cache-Status');
    test()->get('/uncached')->assertHeaderMissing('Response-Cache-Status');
    test()->get('/cached')->assertHeaderMissing('Response-Cache-Status');
    test()->get('/cached')->assertHeaderMissing('Response-Cache-Status');
});
