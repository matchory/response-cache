<?php

namespace Matchory\ResponseCache\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Matchory\ResponseCache\Facades\ResponseCache;
use RuntimeException;

use function array_filter;
use function array_keys;
use function beforeEach;
use function it;
use function str_starts_with;
use function test;

beforeEach(function () {
    $this->app['cache']->flush();
});

it('clears everything via the command', function () {
    test()->get('/cached')->assertContent('1');
    test()->get('/cached')->assertContent('1');

    Artisan::call('response-cache:clear');

    test()->get('/cached')->assertContent('2');
});

it('clears everything with default tags configured', function () {
    $this->app['config']->set('response-cache.tags', ['api']);

    test()->get('/cached')->assertContent('1');
    test()->get('/cached')->assertContent('1');

    Artisan::call('response-cache:clear');

    test()->get('/cached')->assertContent('2');
});

it('flushes one tag without clearing others when default tags are set', function () {
    $this->app['config']->set('response-cache.tags', ['api']);

    test()->get('/users/1')->assertContent('1');
    test()->get('/users/2')->assertContent('2');

    ResponseCache::flush(['user:2']);

    test()->get('/users/1')->assertContent('1');
    test()->get('/users/2')->assertContent('3');
});

it('deletes a URL with default tags configured', function () {
    $this->app['config']->set('response-cache.tags', ['api']);

    test()->get('/cached')->assertContent('1');

    ResponseCache::delete('/cached');

    test()->get('/cached')->assertContent('2');
});

it('leaves foreign cache keys alone on flush-all', function () {
    Cache::store('array')->put('foreign', 'x');

    ResponseCache::flush();

    expect(Cache::store('array')->get('foreign'))->toBe('x');
});

it('never caches a response whose tags were flushed while it was built', function () {
    test()->get('/racing')->assertContent('1');
    test()->get('/racing')->assertContent('2');
    test()->get('/racing')->assertContent('2');
});

it('flushes after commit', function () {
    test()->get('/users/1')->assertContent('1');

    DB::transaction(function () {
        ResponseCache::flush(['users']);

        test()->get('/users/1')->assertContent('1');
    });

    test()->get('/users/1')->assertContent('2');
});

it('drops the flush on rollback', function () {
    test()->get('/users/1')->assertContent('1');

    try {
        DB::transaction(function () {
            ResponseCache::flush(['users']);

            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }

    test()->get('/users/1')->assertContent('1');
});

it('flushes every audience and query variant', function () {
    $user = (new User())->forceFill(['id' => 7]);

    test()->get('/users/1')->assertContent('1');
    test()->get('/users/1?a=1')->assertContent('2');
    test()->actingAs($user)->get('/users/1')->assertContent('3');
    test()->get('/users/1')->assertContent('3');

    ResponseCache::flush(['user:1']);

    test()->get('/users/1')->assertContent('4');
    Auth::forgetUser();
    test()->get('/users/1')->assertContent('5');
    test()->get('/users/1?a=1')->assertContent('6');
});

it('does not touch versions for non-cacheable requests', function () {
    test()->post('/cached-post')->assertContent('1');

    $keys = array_filter(
        array_keys(Cache::store('array')->getStore()->all()),
        static fn(string $key) => str_starts_with($key, 'response-cache:'),
    );

    expect($keys)->toBe([]);
});

describe('on a non-taggable store', function () {
    beforeEach(function () {
        $this->app['config']->set('response-cache.store', 'file');
    });

    afterEach(function () {
        Cache::store('file')->flush();
    });

    it('works on a non-taggable store', function () {
        test()->get('/users/1')->assertContent('1');
        test()->get('/users/1')->assertContent('1');

        ResponseCache::flush(['user:1']);

        test()->get('/users/1')->assertContent('2');
    });
});
