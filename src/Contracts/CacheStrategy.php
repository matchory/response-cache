<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache Strategy
 *
 * @bundle Matchory\ResponseCache
 */
interface CacheStrategy
{
    /**
     * Generates a unique key for a request.
     */
    public function key(Request $request): string;

    /**
     * Determines, before a response exists, whether a request may be served
     * from or stored in the cache.
     */
    public function isCacheable(Request $request): bool;

    /**
     * Determines whether the response should be cached.
     */
    public function shouldCache(Request $request, Response $response): bool;

    /**
     * Generates a list of tags for a request. Tags must be derivable from the
     * request alone, as they are needed to look up a cached response before
     * any response exists.
     *
     * @return string[]
     */
    public function tags(Request $request): array;
}
