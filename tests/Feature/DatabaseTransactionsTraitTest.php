<?php

namespace Matchory\ResponseCache\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Matchory\ResponseCache\Facades\ResponseCache;

use function it;
use function test;
use function uses;

// Applications commonly wrap each test in a transaction; it must not defer
// flushes until the test has ended
uses(DatabaseTransactions::class);

it('flushes immediately inside a test transaction', function () {
    test()->get('/users/1')->assertContent('1');

    ResponseCache::flush(['users']);

    test()->get('/users/1')->assertContent('2');
});

it('flushes after a commit inside a test transaction', function () {
    test()->get('/users/1')->assertContent('1');

    DB::transaction(fn() => ResponseCache::flush(['users']));

    test()->get('/users/1')->assertContent('2');
});
