<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache;

use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\{UrlGenerator, UrlRoutable};
use Illuminate\Http\Request;
use Illuminate\Support\{Carbon, Collection};
use Matchory\ResponseCache\Contracts\CacheStrategy;
use Psr\SimpleCache\InvalidArgumentException;
use Stringable;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_map;
use function array_merge;
use function is_scalar;
use function preg_match_all;
use function sprintf;
use function str_replace;
use function substr;

class ResponseCache
{
    final public const BYPASS_ATTRIBUTE = 'response-cache.bypass';

    /**
     * @var Closure(): Config
     */
    private readonly Closure $configResolver;

    /**
     * Stores resolved tags to speed up repeated queries.
     *
     * @var array<string, string>
     */
    private array $resolvedTags = [];

    /**
     * Creates a new response cache instance.
     *
     * @param callable(): Config $configResolver
     *
     * @internal Should only be invoked by the DI container
     */
    public function __construct(
        callable $configResolver,
        protected readonly UrlGenerator $urlGenerator,
        protected readonly Repository $cache,
        protected readonly CacheStrategy $strategy,
    ) {
        $this->configResolver = $configResolver;
    }

    /**
     * Retrieves a response from the cache. If there is no cached response for
     * the given request, `null` will be returned.
     *
     * @param Request       $request Request to retrieve a response from the
     *                               cache for
     * @param string[]|null $tags    Tags the cache entry is tagged with
     *
     * @return Response|null Response if cached, `null` otherwise
     *
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function get(Request $request, array|null $tags = null): Response|null
    {
        $key = $this->strategy->key($request);
        $tags = $this->resolveTags($tags, $request);

        return $this->cache->get($key, $tags);
    }

    /**
     * Deletes one or more specific URLs from the cache. Note that this can't
     * take bound parameters or tags specific to request instances into account.
     *
     * @param string|string[] $uri  URI or URIs to remove from the cache.
     * @param string[]        $tags Tags to flush the cache for.
     *
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function delete(array|string $uri, array $tags = []): void
    {
        Collection::wrap($uri)->each(function (string $uri) use ($tags) {
            $url = $this->urlGenerator->to($uri);
            /** @noinspection PhpUnhandledExceptionInspection */
            $request = Request::create($url);
            $key = $this->strategy->key($request);

            if ($this->cache->has($key, $tags)) {
                $this->cache->delete($key, $tags);
            }
        });
    }

    /**
     * Checks whether the cache is enabled.
     *
     * @return bool `true` if it is enabled, `false` otherwise
     */
    public function enabled(): bool
    {
        return $this->config()->get('response-cache.enabled', true);
    }

    /**
     * Flushes all cached items, or all items with one or more tags.
     *
     * @param string[]|null $tags Tags to flush. Pass `null` to flush all tags.
     *
     * @throws BadMethodCallException
     */
    public function flush(array|null $tags = null): void
    {
        $tags = array_merge($this->getDefaultTags(), $tags ?? []);

        $this->cache->flush($tags);
    }

    /**
     * Checks whether the response for a request has already been cached.
     *
     * @param Request       $request Request to check the cache status for
     * @param string[]|null $tags    Tags the cached entry is tagged with
     *
     * @return bool `true` if a cached response exists, `false` otherwise
     *
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function has(Request $request, array|null $tags = null): bool
    {
        $key = $this->strategy->key($request);
        $tags = $this->resolveTags($tags, $request);

        return $this->cache->has($key, $tags);
    }

    /**
     * Caches the response to a given request, if it matches the cache strategy.
     *
     * @param Request       $request  Request to cache a response for
     * @param Response      $response Response to cache
     * @param string[]|null $tags     Tags to tag the cache entry with
     * @param int|null      $ttl      Time-to-live for the cache entry
     *
     * @throws BadMethodCallException
     */
    public function put(
        Request $request,
        Response $response,
        array|null $tags = null,
        int|null $ttl = null,
    ): void {
        // Escape hatch to bypass caching via middleware
        if ($request->attributes->has(self::BYPASS_ATTRIBUTE)) {
            return;
        }

        // The strategy has ultimate control over whether responses shall be
        // added to the cache
        if (!$this->strategy->shouldCache($request, $response)) {
            return;
        }

        $ttl = $ttl ?? $this->getDefaultTtl();
        $key = $this->strategy->key($request);
        $tags = $this->resolveTags(
            $tags,
            $request,
            $response,
        );

        $response = $this->addServerTiming($response);
        $response = $this->addCacheStatus($response);

        $this->cache->put($key, $response, $tags, $ttl);
    }

    /**
     * Adds original cache timing information to the response, if enabled.
     *
     * @param Response $response Response instance to add the information to.
     *
     * @return Response New response instance with the header applied to.
     */
    protected function addServerTiming(Response $response): Response
    {
        if (!$this->config()->get('response-cache.server_timing')) {
            return $response;
        }

        $cloned = clone $response;

        $cloned->headers->set(
            'Server-Timing',
            sprintf(
                'response-cache;desc="%s"',
                Carbon::now()->toRfc2822String(),
            ),
            replace: false,
        );

        return $cloned;
    }

    protected function addCacheStatus(Response $response): Response
    {
        if (!$this->config()->get('response-cache.cache_status_enabled')) {
            return $response;
        }

        $response->headers->set('Response-Cache-Status', 'hit');

        return $response;
    }

    /**
     * Retrieves the default tags as specified in the configuration file.
     *
     * @return string[] List of tags
     */
    protected function getDefaultTags(): array
    {
        return $this->config()->get('response-cache.tags', []);
    }

    /**
     * Retrieves the default TTL as specified in the configuration file.
     *
     * @return int|null Time-to-live in seconds if configured, `null` otherwise
     */
    protected function getDefaultTtl(): int|null
    {
        return $this->config()->get('response-cache.ttl');
    }

    /**
     * Replaces bound parameters inside tag strings with the corresponding value
     * from the request instance. This allows using Laravel's implicit route
     * bindings in cache tags, thus having a simple way to scope cache tags
     * automatically.
     * Note that, for this to work properly, the cache middleware _must_ run
     * after the SubstituteBindings middleware.
     *
     * @param string  $tag     Tag to replace bindings in.
     * @param Request $request Request instance to fetch bound parameters from.
     *
     * @return string Finalized tag.
     *
     * @example Assume a tag passed as "users.{user}". The route has an implicit
     *          binding to the user model: `/users/{user}`. Now what will happen
     *          is that we detect `{user}` as a potential binding parameter, and
     *          fetch the value for the `user` parameter from the request, which
     *          yields an instance of `App\User`. As that is an Eloquent model,
     *          we replace it with its route key, which will probably be its ID
     *          or UUID (let's assume `42`). Therefore, after iterating through,
     *          we will return the following, finalized cache tag: `users.42`!
     */
    protected function replaceBinding(string $tag, Request $request): string
    {
        if (isset($this->resolvedTags[$tag])) {
            return $this->resolvedTags[$tag];
        }

        // If there is no opening curly brace in the tag, there's no need to run
        // a regular expression match
        if (!str_contains($tag, '{')) {
            return $tag;
        }

        // Extract all bindings from the tag. This will yield a nested array
        // containing all matched bindings
        preg_match_all('/{.+?}/', $tag, $matches);

        if (!isset($matches[0])) {
            return $tag;
        }

        foreach ($matches[0] as $match) {
            // Remove the curly braces from the match to retrieve the parameter
            // name, which we need to read the value from the request
            $name = substr($match, 1, -1);

            $value = $request->route($name)
                ?? $request->get($name)
                ?? null;

            if (!$value) {
                continue;
            }

            // Handle Eloquent models by replacing them with their route key in
            // the cache tag
            if ($value instanceof UrlRoutable) {
                $value = $value->getRouteKey();
            }

            // We can't replace non-scalar values, so to prevent errors in the
            // `str_replace` call below, we skip other values
            if (
                !is_scalar($value) &&
                !($value instanceof Stringable)
            ) {
                continue;
            }

            // Replace the matched expression (`{foo}`) with the value found in
            // the request inside the tag
            $tag = str_replace($match, $value, $tag);
        }

        return $this->resolvedTags[$tag] = $tag;
    }

    /**
     * Resolves all tags to apply to a given request/response pair, and replaces
     * bound parameters in all cache tags.
     *
     * @param string[]|null $tags     Individual tags to add to this response.
     * @param Request       $request  Request instance.
     * @param Response|null $response Response instance.
     *
     * @return string[] List of finalized tags.
     */
    protected function resolveTags(
        array|null $tags,
        Request $request,
        Response|null $response = null,
    ): array {
        $resolved = array_merge(
            $this->strategy->tags($request, $response),
            $this->getDefaultTags(),
            $tags ?? [],
        );

        return array_map(fn(string $tag) => $this->replaceBinding(
            $tag,
            $request,
        ), array_filter($resolved));
    }

    private function config(): Config
    {
        return ($this->configResolver)();
    }
}
