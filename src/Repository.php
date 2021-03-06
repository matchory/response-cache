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
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use JetBrains\PhpStorm\Pure;
use Matchory\ResponseCache\Events\Flush;
use Psr\SimpleCache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache Repository
 * ================
 * Responsible for cache persistence.
 *
 * @package Matchory\ResponseCache
 */
class Repository
{
    #[Pure]
    public function __construct(
        protected CacheRepository $store,
        protected Dispatcher $eventDispatcher
    ) {
    }

    /**
     * Retrieves a response from the cache.
     *
     * @param string        $key
     * @param string[]|null $tags
     *
     * @return Response|null
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function get(string $key, array|null $tags = null): Response|null
    {
        $response = $this->getStore($tags)->get($key);

        if ( ! $response) {
            return null;
        }

        return $this->hydrate($response);
    }

    /**
     * Checks whether a response is cached.
     *
     * @param string        $key
     * @param string[]|null $tags
     *
     * @return bool
     * @throws BadMethodCallException
     * @throws InvalidArgumentException
     */
    public function has(string $key, array|null $tags = null): bool
    {
        return $this->getStore($tags)->has($key);
    }

    /**
     * Puts a response into the cache.
     *
     * @param string        $key
     * @param Response      $response
     * @param string[]|null $tags
     * @param int|null      $ttl
     *
     * @throws BadMethodCallException
     */
    public function put(
        string $key,
        Response $response,
        array|null $tags = null,
        int|null $ttl = null
    ): void {
        $serialized = $this->serialize($response);

        $this->getStore($tags)->put($key, $serialized, $ttl);
    }

    /**
     * Flushes the response cache.
     *
     * @param string[]|null $tags
     *
     * @throws BadMethodCallException
     */
    public function flush(array|null $tags = null): void
    {
        $this->getStore($tags)->clear();

        $this->eventDispatcher->dispatch(new Flush($tags));
    }

    /**
     * Deletes a cached response.
     *
     * @param string        $key
     * @param string[]|null $tags
     *
     * @throws BadMethodCallException
     */
    public function delete(string $key, array|null $tags = null): void
    {
        $this->getStore($tags)->forget($key);
    }

    /**
     * Hydrates a serialized response. This method may be overridden by children
     * implementations to add custom hydration mechanisms.
     *
     * @param mixed $cached Cached representation of the response.
     *
     * @return Response Hydrated response instance.
     */
    protected function hydrate(mixed $cached): Response
    {
        return $cached;
    }

    /**
     * Serializes the response. This method may be overridden by children
     * implementations to add custom serialization mechanisms.
     *
     * @param Response $response Response instance.
     *
     * @return mixed Serialized response representation.
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     */
    protected function serialize(Response $response): mixed
    {
        return $response;
    }

    /**
     * Retrieves the store instance. If it supports tags, a tagged instance for
     * the given tags will be returned, the untagged store otherwise.
     *
     * @param string[]|null $tags
     *
     * @return CacheRepository
     * @throws BadMethodCallException
     */
    protected function getStore(array|null $tags = null): CacheRepository
    {
        return $this->supportsTags()
            ? $this->store->tags($tags)
            : $this->store;
    }

    /**
     * Checks whether the store supports tags.
     *
     * @return bool
     */
    protected function supportsTags(): bool
    {
        return $this->store->getStore() instanceof TaggableStore;
    }
}
