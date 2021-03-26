<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache;

use BadMethodCallException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use JetBrains\PhpStorm\Pure;
use Matchory\ResponseCache\Contracts\CacheStrategy;
use Psr\SimpleCache\InvalidArgumentException;

use function array_filter;
use function array_merge;

class ResponseCache
{
    public const BYPASS_ATTRIBUTE = 'response-cache.bypass';

    /**
     * Whether the cache is enabled.
     *
     * @var bool
     */
    protected bool $enabled;

    /**
     * Creates a new response cache instance.
     *
     * @param Config        $config
     * @param UrlGenerator  $urlGenerator
     * @param Repository    $cache
     * @param CacheStrategy $strategy
     * @param Application   $app
     *
     * @internal Should only be invoked by the DI container
     */
    public function __construct(
        protected Config $config,
        protected UrlGenerator $urlGenerator,
        protected Repository $cache,
        protected CacheStrategy $strategy,
        Application $app
    ) {
        $this->enabled = (
            $this->config->get('response-cache.enabled') ||
            ! $app->environment('testing') ||
            $app->runningUnitTests() ||
            $app->runningInConsole()
        );
    }

    /**
     * Checks whether the cache is enabled.
     *
     * @return bool `true` if it is enabled, `false` otherwise
     */
    #[Pure]
    public function enabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Checks whether the response for a request has already been cached.
     *
     * @param Request       $request Request to check the cache status for
     * @param string[]|null $tags    Tags the cached entry is tagged with
     *
     * @return bool `true` if a cached response exists, `false` otherwise
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function has(Request $request, array|null $tags = null): bool
    {
        $key = $this->strategy->key($request);
        $tags = array_merge(
            $this->strategy->tags($request),
            $this->getDefaultTags(),
            $tags,
        );

        return $this->cache->has($key, array_filter($tags));
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
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function get(
        Request $request,
        array|null $tags = null
    ): Response|null {
        $key = $this->strategy->key($request);
        $tags = array_merge($tags, $this->strategy->tags(
            $request
        ));

        return $this->cache->get($key, array_filter($tags));
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
        int|null $ttl = null
    ): void {
        // Escape hatch to bypass caching via middleware
        if ($request->attributes->has(self::BYPASS_ATTRIBUTE)) {
            return;
        }

        // The strategy has ultimate control over whether responses shall be
        // added to the cache
        if ( ! $this->strategy->shouldCache(
            $request,
            $response
        )) {
            return;
        }

        $ttl = $ttl ?? $this->getDefaultTtl();
        $key = $this->strategy->key($request);
        $tags = array_merge(
            $this->strategy->tags($request, $response),
            $this->getDefaultTags(),
            $tags ?? [],
        );

        $response = $this->addServerTiming($response);

        $this->cache->put(
            $key,
            $response,
            array_filter($tags),
            $ttl
        );
    }

    /**
     * @param string|string[] $uri
     * @param string[]        $tags
     *
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function delete(array|string $uri, array $tags = []): void
    {
        $uris = (array)$uri;

        Collection::make($uris)->each(function (
            string $uri
        ) use ($tags): void {
            $url = $this->urlGenerator->to($uri);
            $request = Request::create($url);
            $key = $this->strategy->key($request);

            if ($this->cache->has($key, $tags)) {
                $this->cache->delete($key, $tags);
            }
        });
    }

    /**
     * @param array|null $tags
     *
     * @throws BadMethodCallException
     */
    public function flush(array|null $tags = null): void
    {
        $tags = array_merge(
            $this->getDefaultTags(),
            $tags ?? [],
        );

        $this->cache->flush($tags);
    }

    protected function addServerTiming(Response $response): Response
    {
        if ( ! $this->config->get('response-cache.server_timing')) {
            return $response;
        }

        $cloned = clone $response;

        $cloned->headers->set('Server-Timing', sprintf(
            'response-cache;desc="%s"',
            Carbon::now()->toRfc2822String()
        ));

        return $cloned;
    }

    /**
     * Retrieves the default TTL as specified in the configuration file.
     *
     * @return int|null Time-to-live in seconds if configured, `null` otherwise
     */
    protected function getDefaultTtl(): int|null
    {
        return $this->config->get('response-cache.ttl');
    }

    /**
     * Retrieves the default tags as specified in the configuration file.
     *
     * @return string[] List of tags
     */
    protected function getDefaultTags(): array
    {
        return $this->config->get('response-cache.tags', []);
    }
}
