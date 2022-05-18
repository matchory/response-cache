<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Matchory\ResponseCache\ResponseCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bypass Cache Middleware
 *
 * If added to a route, the response cache will be bypassed.
 *
 * @bundle Matchory\ResponseCache
 */
class BypassCache
{
    /**
     * @param Request $request
     * @param Closure $next
     *
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->add([
            ResponseCache::BYPASS_ATTRIBUTE => true,
        ]);

        return $next($request);
    }
}
