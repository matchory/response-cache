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

namespace Matchory\ResponseCache\Tests;

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
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\Generator\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\Generator\ClassIsEnumerationException;
use PHPUnit\Framework\MockObject\Generator\ClassIsFinalException;
use PHPUnit\Framework\MockObject\Generator\ClassIsReadonlyException;
use PHPUnit\Framework\MockObject\Generator\DuplicateMethodException;
use PHPUnit\Framework\MockObject\Generator\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\Generator\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\Generator\ReflectionException;
use PHPUnit\Framework\MockObject\Generator\RuntimeException;
use PHPUnit\Framework\MockObject\Generator\UnknownTypeException;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResponseCache::class)]
class ResponseCacheTest extends TestCase
{
    /**
     * @throws BadMethodCallException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws ClassAlreadyExistsException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function testCachesResponses(): void
    {
        $configResolver = $this->createConfigResolver([
            'response-cache.enabled' => true,
            'response-cache.ttl' => 42,
        ]);
        $urlGenerator = $this
            ->getMockBuilder(UrlGenerator::class)
            ->getMock();
        $cache = $this->createRepository();
        $strategy = $this->createStrategy();
        $responseCache = new ResponseCache(
            $configResolver,
            $urlGenerator,
            $cache,
            $strategy,
        );

        $response = new Response('foo');

        self::assertFalse($responseCache->has(Request::create('/')));
        $responseCache->put(Request::create('/'), $response);
        self::assertTrue($responseCache->has(Request::create('/')));
        self::assertSame(
            $response,
            $responseCache->get(Request::create('/'))
        );
    }

    /**
     * @param array<string, mixed>|null $values
     *
     * @return callable(): Config
     * @throws InvalidArgumentException
     * @throws ClassAlreadyExistsException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     */
    private function createConfigResolver(array|null $values = null): callable
    {
        $config = $this
            ->getMockBuilder(Config::class)
            ->getMock();
        $config->method('get')->willReturnCallback(
            fn(string $key, mixed $default = null) => $values[$key] ?? $default
        );

        return static fn(): Config => $config;
    }

    /**
     * @return Repository
     * @throws ClassAlreadyExistsException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
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
     * @return BaseStrategy
     * @throws ClassAlreadyExistsException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
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
}
