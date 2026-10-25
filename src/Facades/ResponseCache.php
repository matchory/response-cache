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
 * @method static \Symfony\Component\HttpFoundation\Response|null get(\Illuminate\Http\Request $request, string[]|null $tags = null) Retrieves a cached response
 * @method static bool has(\Illuminate\Http\Request $request, string[]|null $tags = null) Checks whether a response is cached
 * @method static void put(\Illuminate\Http\Request $request, \Symfony\Component\HttpFoundation\Response $response, string[]|null $tags = null, int|null $ttl = null) Caches a response
 * @method static void flush(string[]|null $tags = null) Flushes the cache
 * @method static void delete(string|string[] $uri, string[] $tags = []) Deletes one or more URIs
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
