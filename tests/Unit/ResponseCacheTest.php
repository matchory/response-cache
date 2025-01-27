<?php

/**
 * This file is part of response-cache, a Matchory application.
 *
 * Unauthorized copying of this file, via any medium, is strictly prohibited.
 * Its contents are strictly confidential and proprietary.
 *
 * @copyright 2020–2023 Matchory GmbH · All rights reserved
 * @author    Moritz Friedrich <moritz@matchory.com>
 */

declare(strict_types=1);

namespace Matchory\ResponseCache\Tests\Unit;

use BadMethodCallException;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepositoryImplementation;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Matchory\ResponseCache\Repository;
use Matchory\ResponseCache\ResponseCache;
use Matchory\ResponseCache\Support\BaseStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\Generator\ClassIsEnumerationException;
use PHPUnit\Framework\MockObject\Generator\ClassIsFinalException;
use PHPUnit\Framework\MockObject\Generator\DuplicateMethodException;
use PHPUnit\Framework\MockObject\Generator\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\Generator\NameAlreadyInUseException;
use PHPUnit\Framework\MockObject\Generator\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\Generator\ReflectionException;
use PHPUnit\Framework\MockObject\Generator\RuntimeException;
use PHPUnit\Framework\MockObject\Generator\UnknownTypeException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

#[CoversClass(ResponseCache::class)]
class ResponseCacheTest extends TestCase
{
    /**
     * @throws BadMethodCallException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws NameAlreadyInUseException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws BadRequestException
     */
    #[Test]
    public function cachesResponses(): void
    {
        $responseCache = $this->createResponseCache();
        $response = new Response('foo');

        self::assertFalse($responseCache->has(Request::create('/')));
        $responseCache->put(Request::create('/'), $response);
        self::assertTrue($responseCache->has(Request::create('/')));
        self::assertSame(
            $response,
            $responseCache->get(Request::create('/')),
        );
    }

    /**
     * @throws BadMethodCallException
     * @throws BadRequestException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws NameAlreadyInUseException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    #[Test]
    public function addsStatusHeader(): void
    {
        $responseCache = $this->createResponseCache([
            'response-cache.cache_status_enabled' => true,
        ]);
        $responseCache->put(Request::create('/'), new Response('foo'));

        self::assertSame(
            'hit',
            $responseCache
                ->get(Request::create('/'))
                ->headers
                ->get('Response-Cache-Status'),
        );
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return callable(): Config
     *
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws NameAlreadyInUseException
     */
    private function createConfigResolver(array|null $values = null): callable
    {
        $config = $this
            ->getMockBuilder(Config::class)
            ->getMock();
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $values[$key] ?? $default,
        );

        return static fn(): Config => $config;
    }

    /**
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws NameAlreadyInUseException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    private function createRepository(): Repository
    {
        $cache = new CacheRepositoryImplementation(new ArrayStore());
        $dispatcher = $this->getMockBuilder(Dispatcher::class)->getMock();

        return new Repository($cache, $dispatcher);
    }

    /**
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws NameAlreadyInUseException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    private function createStrategy(): BaseStrategy
    {
        $auth = $this
            ->getMockBuilder(AuthManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        return new BaseStrategy($auth);
    }

    /**
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws NameAlreadyInUseException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    private function createResponseCache(array|null $config = null): ResponseCache
    {
        $config ??= [
            'response-cache.enabled' => true,
            'response-cache.ttl' => 42,
        ];
        $configResolver = $this->createConfigResolver($config);
        $urlGenerator = $this
            ->getMockBuilder(UrlGenerator::class)
            ->getMock();
        $cache = $this->createRepository();
        $strategy = $this->createStrategy();

        return new ResponseCache(
            $configResolver,
            $urlGenerator,
            $cache,
            $strategy,
        );
    }
}
