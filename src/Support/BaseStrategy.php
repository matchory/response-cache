<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * @copyright 2020–2021 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Support;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JetBrains\PhpStorm\Pure;
use Matchory\ResponseCache\Contracts\CacheStrategy;

use function md5;

class BaseStrategy implements CacheStrategy
{
    /**
     * Default tags to apply to all cache entries based on the config file.
     *
     * @var string[]
     */
    protected array $defaultTags;

    public function __construct(
        protected AuthManager $auth,
        protected Repository $config
    ) {
        $this->defaultTags = $this->config->get(
            'response-cache.tags',
            []
        );
    }

    /**
     * @inheritDoc
     */
    public function key(Request $request): string
    {
        $identifier = $this->extractRequestIdentifier($request);
        $suffix = $this->buildSuffix($request);

        return $this->hash($identifier . $suffix);
    }

    /**
     * @inheritDoc
     */
    public function shouldCache(
        Request $request,
        Response $response
    ): bool {
        if ( ! $this->isMethodCachable($request)) {
            return false;
        }

        return $this->isSuccessful($response);
    }

    /**
     * @inheritDoc
     */
    #[Pure]
    public function tags(
        Request $request,
        Response|null $response = null
    ): array {
        return $this->defaultTags;
    }

    /**
     * Checks whether the request method if a request is safe to cache.
     *
     * @param Request $request Request instance.
     *
     * @return bool Whether the method is safe to cache.
     */
    protected function isMethodCachable(Request $request): bool
    {
        return $request->isMethodCacheable();
    }

    /**
     * Checks whether a response is successful. The default implementation will
     * succeed if the status code is in the 2xx-3xx range.
     *
     * @param Response $response Response instance.
     *
     * @return bool Whether the response is successful
     */
    #[Pure]
    protected function isSuccessful(
        Response $response
    ): bool {
        return $response->isSuccessful() || $response->isRedirection();
    }

    /**
     * Extracts a unique identifier from a request. The default implementation
     * will use the full URL as provided by Laravel.
     *
     * @param Request $request Current request instance.
     *
     * @return string Unique request identifier.
     */
    protected function extractRequestIdentifier(Request $request): string
    {
        return $request->method() . $request->fullUrl();
    }

    /**
     * Retrieves a suffix to append to the key before it is hashed. This allows
     * to constrain the cache key to the authenticated user, for example.
     *
     * @param Request $request
     *
     * @return string
     * @noinspection PhpUndefinedMethodInspection
     * @noinspection PhpUnusedParameterInspection
     */
    protected function buildSuffix(Request $request): string
    {
        return $this->auth->check()
            ? (string)$this->auth->id()
            : '';
    }

    /**
     * Hashes the cache key. The default implementation will return an MD5 hash
     * of the given key.
     *
     * @param string $key
     *
     * @return string
     */
    #[Pure]
    protected function hash(
        string $key
    ): string {
        return md5($key);
    }
}
