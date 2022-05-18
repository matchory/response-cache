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
     *
     * @param Request $request
     *
     * @return string
     */
    public function key(Request $request): string;

    /**
     * Determines whether the response should be cached.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return bool
     */
    public function shouldCache(Request $request, Response $response): bool;

    /**
     * Generates a list of tags for a request. If the cache is missed, this
     * method will be invoked with the generated response passed, providing an
     * opportunity to add tags dependent on the generated response, for example
     * the ID of a customer associated to the request, or similar.
     *
     * @param Request       $request
     * @param Response|null $response
     *
     * @return string[]
     */
    public function tags(Request $request, Response|null $response = null): array;
}
