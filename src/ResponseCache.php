<?php

/**
 * This file is part of response-cache, a Matchory library.
 *
 * @author Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\{UrlGenerator, UrlRoutable};
use Illuminate\Http\Request;
use Illuminate\Support\{Carbon, Collection};
use Matchory\ResponseCache\Contracts\CacheStrategy;
use Psr\SimpleCache\InvalidArgumentException;
use Random\RandomException;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
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
     * @var Closure(callable): void
     */
    private readonly Closure $afterCommit;

    /**
     * Creates a new response cache instance.
     *
     * @param callable(): Config       $configResolver
     * @param callable(callable): void $afterCommit    Runs a callback once the
     *                                                 current database
     *                                                 transaction commits
     *
     * @internal Should only be invoked by the DI container
     */
    public function __construct(
        callable $configResolver,
        callable $afterCommit,
        protected readonly UrlGenerator $urlGenerator,
        protected readonly Repository $cache,
        protected readonly CacheStrategy $strategy,
    ) {
        $this->configResolver = $configResolver(...);
        $this->afterCommit = $afterCommit(...);
    }

    /**
     * Checks, before a response exists, whether a request may be served from
     * or stored in the cache.
     */
    public function isCacheable(Request $request): bool
    {
        return $this->strategy->isCacheable($request);
    }

    /**
     * Looks up the response to a request in the cache. On a miss, the lookup
     * carries the snapshot to pass to {@see put()} once the response is built.
     *
     * @param Request  $request Request to look up a response for
     * @param string[] $tags    Additional tags of the cache entry
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function lookup(Request $request, array $tags = []): Lookup
    {
        return $this->cache->lookup(
            $this->strategy->key($request),
            $this->resolveTags($tags, $request),
        );
    }

    /**
     * Retrieves a response from the cache. If there is no cached response for
     * the given request, `null` will be returned.
     *
     * @param Request  $request Request to retrieve a response from the cache
     *                          for
     * @param string[] $tags    Additional tags of the cache entry
     *
     * @return Response|null Response if cached, `null` otherwise
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function get(Request $request, array $tags = []): ?Response
    {
        return $this->lookup($request, $tags)->response;
    }

    /**
     * Deletes one or more specific URLs from the cache. As cache keys depend
     * on the strategy, this only removes the variant the strategy computes for
     * the current context, for example the authenticated user.
     *
     * @param string|string[] $uri URI or URIs to remove from the cache.
     *
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function delete(array|string $uri): void
    {
        Collection::wrap($uri)->each(function (string $uri) {
            $url = $this->urlGenerator->to($uri);
            /** @noinspection PhpUnhandledExceptionInspection */
            $request = Request::create($url);

            $this->cache->delete($this->strategy->key($request));
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
     * Flushes all cached items, or all items with one or more tags. If a
     * database transaction is open, the flush runs once it commits, and not at
     * all if it rolls back.
     *
     * @param string[]|null $tags Tags to flush. Pass `null` to flush all
     *                            items; an empty array flushes nothing.
     *
     * @throws RandomException
     * @throws RuntimeException If the store fails to write a tag version
     */
    public function flush(?array $tags = null): void
    {
        if ($tags === []) {
            return;
        }

        ($this->afterCommit)(fn() => $this->cache->flush($tags));
    }

    /**
     * Captures the current tag versions for a request. Pass the snapshot to
     * {@see put()} once the response is built, so a flush that happens in the
     * meantime invalidates it.
     *
     * @param Request  $request Request to take a snapshot for
     * @param string[] $tags    Additional tags of the cache entry
     *
     * @return Snapshot|null Snapshot, or `null` if the tag versions could not
     *                       be established.
     *
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function snapshot(Request $request, array $tags = []): ?Snapshot
    {
        return $this->cache->snapshot($this->resolveTags($tags, $request));
    }

    /**
     * Caches the response to a given request, if it matches the cache strategy.
     *
     * @param Request  $request  Request to cache a response for
     * @param Response $response Response to cache
     * @param Snapshot $snapshot Tag versions captured before the response was
     *                           built, see {@see lookup()} and {@see snapshot()}
     * @param int|null $ttl      Time-to-live for the cache entry
     */
    public function put(
        Request $request,
        Response $response,
        Snapshot $snapshot,
        ?int $ttl = null,
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

        $response = $this->addServerTiming($response);
        $response = $this->addCacheStatus($response);

        $this->cache->put($key, $response, $snapshot, $ttl);
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
    protected function getDefaultTtl(): ?int
    {
        $ttl = $this->config()->get('response-cache.ttl');

        // Values from the environment are strings
        return $ttl === null ? null : (int) $ttl;
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
                ?? $request->input($name);

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
                !is_scalar($value)
                && !($value instanceof Stringable)
            ) {
                continue;
            }

            // Replace the matched expression (`{foo}`) with the value found in
            // the request inside the tag
            $tag = str_replace($match, $value, $tag);
        }

        return $tag;
    }

    /**
     * Resolves all tags to apply to a given request, and replaces bound
     * parameters in all cache tags.
     *
     * @param string[] $tags    Individual tags to add to this request.
     * @param Request  $request Request instance.
     *
     * @return string[] List of finalized tags.
     */
    protected function resolveTags(array $tags, Request $request): array
    {
        $resolved = array_merge(
            $this->strategy->tags($request),
            $this->getDefaultTags(),
            $tags,
        );

        return array_values(array_unique(array_map(
            fn(string $tag) => $this->replaceBinding($tag, $request),
            array_filter($resolved),
        )));
    }

    private function config(): Config
    {
        return ($this->configResolver)();
    }
}
