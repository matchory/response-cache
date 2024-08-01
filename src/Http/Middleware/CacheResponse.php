<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Http\Middleware;

use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Matchory\ResponseCache\Events\Hit;
use Matchory\ResponseCache\Events\Miss;
use Matchory\ResponseCache\ResponseCache;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

use function array_shift;
use function is_numeric;

/**
 * Cache Response Middleware
 *
 * Will cache responses generated by the middleware stack and return the cached
 * response on subsequent requests.
 *
 * @bundle Matchory\ResponseCache
 */
readonly class CacheResponse
{
    /**
     * Creates a new middleware instance.
     *
     * @param Dispatcher    $eventDispatcher
     * @param ResponseCache $responseCache
     *
     * @internal Should only be invoked by the DI container
     */
    public function __construct(
        protected Dispatcher $eventDispatcher,
        protected ResponseCache $responseCache
    ) {
    }

    /**
     * Handles requests.
     *
     * @param Request $request
     * @param Closure $next
     * @param string  ...$args
     *
     * @return Response
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function handle(
        Request $request,
        Closure $next,
        string ...$args
    ): Response {
        if ( ! $this->responseCache->enabled()) {
            return $next($request);
        }

        // If the first value passed to the middleware is a number, we'll use it
        // as the TTL. Everything subsequent parameter is interpreted as a tag.
        $ttl = isset($args[0]) && is_numeric($args[0])
            ? (int)array_shift($args)
            : null;
        $tags = $args;

        if (
            $this->responseCache->has($request, $tags) &&
            ($response = $this->responseCache->get($request, $tags)) !== null
        ) {
            $this->eventDispatcher->dispatch(new Hit($request));

            return $response;
        }

        $response = $next($request);

        $this->eventDispatcher->dispatch(new Miss($request));

        $this->responseCache->put(
            $request,
            clone $response,
            $tags,
            $ttl
        );

        return $response;
    }
}
