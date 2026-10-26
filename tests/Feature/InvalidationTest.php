<?php

namespace Matchory\ResponseCache\Tests\Feature;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
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

it('clears everything via the command', function (array $defaultTags) {
    $this->app['config']->set('response-cache.tags', $defaultTags);

    test()->get('/cached')->assertContent('1');
    test()->get('/cached')->assertContent('1');

    Artisan::call('response-cache:clear');

    test()->get('/cached')->assertContent('2');
})->with([
    'without default tags' => [[]],
    'with default tags' => [['api']],
]);

it('flushes one tag without clearing others when default tags are set', function () {
    $this->app['config']->set('response-cache.tags', ['api']);

    test()->get('/users/1')->assertContent('1');
    test()->get('/users/2')->assertContent('2');

    ResponseCache::flush(['user:2']);

    test()->get('/users/1')->assertContent('1');
    test()->get('/users/2')->assertContent('3');
});

it('parses the leading middleware argument as TTL and the rest as tags', function () {
    test()->get('/users/1')->assertContent('1');
    test()->get('/users/2')->assertContent('2');

    ResponseCache::flush(['users']);

    test()->get('/users/1')->assertContent('3');
    test()->get('/users/2')->assertContent('4');

    Carbon::setTestNow(Carbon::now()->addSeconds(61));

    test()->get('/users/1')->assertContent('5');

    Carbon::setTestNow();
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

it('flushes after rollback', function () {
    test()->get('/users/1')->assertContent('1');

    try {
        DB::transaction(function () {
            ResponseCache::flush(['users']);

            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }

    test()->get('/users/1')->assertContent('2');
});

it('flushes when an unrelated transaction rolls back', function () {
    test()->get('/users/1')->assertContent('1');

    // The data was written outside the transaction that is open on another
    // connection, so rolling that one back must not lose the flush
    try {
        DB::connection('other')->transaction(function () {
            ResponseCache::flush(['users']);

            test()->get('/users/1')->assertContent('1');

            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
    }

    test()->get('/users/1')->assertContent('2');
});

it('flushes once transactions on all connections have ended', function () {
    test()->get('/users/1')->assertContent('1');

    DB::transaction(function () {
        DB::connection('other')->transaction(function () {
            ResponseCache::flush(['users']);
        });

        test()->get('/users/1')->assertContent('1');
    });

    test()->get('/users/1')->assertContent('2');
});

it('flushes after a nested transaction commits with its parent', function () {
    test()->get('/users/1')->assertContent('1');

    DB::transaction(function () {
        DB::transaction(function () {
            ResponseCache::flush(['users']);
        });

        test()->get('/users/1')->assertContent('1');
    });

    test()->get('/users/1')->assertContent('2');
});

it('waits for the parent when a nested transaction rolls back', function () {
    test()->get('/users/1')->assertContent('1');

    DB::transaction(function () {
        try {
            DB::transaction(function () {
                ResponseCache::flush(['users']);

                throw new RuntimeException('Rollback');
            });
        } catch (RuntimeException) {
        }

        test()->get('/users/1')->assertContent('1');
    });

    test()->get('/users/1')->assertContent('2');
});

it('reports a failed deferred flush without breaking the commit', function () {
    Exceptions::fake();
    Cache::extend('rejecting', fn() => new Repository(new class extends ArrayStore {
        public function put($key, $value, $seconds)
        {
            return false;
        }
    }));
    $this->app['config']->set('cache.stores.rejecting', ['driver' => 'rejecting']);
    $this->app['config']->set('response-cache.store', 'rejecting');
    $committed = false;

    DB::transaction(function () use (&$committed) {
        ResponseCache::flush(['users']);

        DB::afterCommit(function () use (&$committed) {
            $committed = true;
        });
    });

    expect($committed)->toBeTrue();
    Exceptions::assertReported(RuntimeException::class);
});

it('throws a failed flush outside transactions', function () {
    Cache::extend('rejecting', fn() => new Repository(new class extends ArrayStore {
        public function put($key, $value, $seconds)
        {
            return false;
        }
    }));
    $this->app['config']->set('cache.stores.rejecting', ['driver' => 'rejecting']);
    $this->app['config']->set('response-cache.store', 'rejecting');

    ResponseCache::flush(['users']);
})->throws(RuntimeException::class);

it('does not write to the store when reading', function () {
    expect(ResponseCache::get(Request::create('/cached')))->toBeNull();

    $keys = array_filter(
        array_keys(Cache::store('array')->getStore()->all()),
        static fn(string $key) => str_starts_with($key, 'response-cache:'),
    );

    expect($keys)->toBe([]);
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

it('works on a non-taggable store', function () {
    $this->app['config']->set('response-cache.store', 'file');

    try {
        test()->get('/users/1')->assertContent('1');
        test()->get('/users/1')->assertContent('1');

        ResponseCache::flush(['user:1']);

        test()->get('/users/1')->assertContent('2');
    } finally {
        Cache::store('file')->flush();
    }
});
