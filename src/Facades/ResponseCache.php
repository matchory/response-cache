<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Facades;

use Illuminate\Support\Facades\Facade;
use Matchory\ResponseCache\ResponseCache as Instance;

/**
 * Response Cache Facade
 *
 * @method static bool isCacheable(\Illuminate\Http\Request $request) Checks whether a request may be cached
 * @method static \Matchory\ResponseCache\Lookup lookup(\Illuminate\Http\Request $request, string[] $tags = []) Looks up a cached response
 * @method static \Symfony\Component\HttpFoundation\Response|null get(\Illuminate\Http\Request $request, string[] $tags = []) Retrieves a cached response
 * @method static \Matchory\ResponseCache\Snapshot|null snapshot(\Illuminate\Http\Request $request, string[] $tags = []) Captures the current tag versions
 * @method static void put(\Illuminate\Http\Request $request, \Symfony\Component\HttpFoundation\Response $response, \Matchory\ResponseCache\Snapshot $snapshot, int|null $ttl = null) Caches a response
 * @method static void flush(string[]|null $tags = null) Flushes the cache
 * @method static void delete(string|string[] $uri) Deletes one or more URIs
 * @method static bool enabled() Checks whether the cache is enabled
 *
 * @bundle Matchory\ResponseCache
 *
 * @see    Instance
 */
class ResponseCache extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Instance::class;
    }
}
