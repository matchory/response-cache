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
use Illuminate\Http\Response;
use JetBrains\PhpStorm\Pure;
use Matchory\ResponseCache\Events\Flush;
use Psr\SimpleCache\InvalidArgumentException;

class Repository
{
    #[Pure]
    public function __construct(
        protected CacheRepository $store,
        protected Dispatcher $eventDispatcher
    ) {
    }

    /**
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
     * @param string        $key
     * @param string[]|null $tags
     *
     * @throws BadMethodCallException
     */
    public function delete(string $key, array|null $tags = null): void
    {
        $this->getStore($tags)->forget($key);
    }

    protected function hydrate(mixed $cached): Response
    {
        return $cached;
    }

    /**
     * @param Response $response
     *
     * @return mixed
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     */
    protected function serialize(Response $response): mixed
    {
        return $response;
    }

    /**
     * @param string[]|null $tags
     *
     * @return CacheRepository
     * @throws BadMethodCallException
     */
    protected function getStore(array|null $tags = null): CacheRepository
    {
        return $this->isTagged()
            ? $this->store->tags($tags)
            : $this->store;
    }

    #[Pure]
    protected function isTagged(): bool
    {
        return $this->store instanceof TaggableStore;
    }
}
