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

namespace Matchory\ResponseCache\Tests\Unit\Support;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Matchory\ResponseCache\Support\BaseStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
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

#[CoversClass(BaseStrategy::class)]
class BaseStrategyTest extends TestCase
{
    /**
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
     */
    public function testResolvesKey(): void
    {
        $mock = $this->getMockBuilder(AuthManager::class);
        $mock->disableOriginalConstructor();
        $auth = $mock->getMock();
        $strategy = new BaseStrategy($auth);
        $key = $strategy->key(Request::create('/'));

        self::assertSame('5a3955530bb1b1d6705bce5197bb6fee', $key);
    }

    /**
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
     */
    public function testResolvesKeyForPath(): void
    {
        $mock = $this->getMockBuilder(AuthManager::class);
        $mock->disableOriginalConstructor();
        $auth = $mock->getMock();
        $strategy = new BaseStrategy($auth);
        $key = $strategy->key(Request::create('/foo/bar/baz'));

        self::assertSame('08561e29a8a4c6c29a93d0c0f719ef46', $key);
    }

    /**
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws DuplicateMethodException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws NameAlreadyInUseException
     * @throws BadRequestException
     */
    public function testResolvesKeyReproducibly(): void
    {
        $mock = $this->getMockBuilder(AuthManager::class);
        $mock->disableOriginalConstructor();
        $auth = $mock->getMock();
        $strategy = new BaseStrategy($auth);
        $key1 = $strategy->key(Request::create('/'));
        $key2 = $strategy->key(Request::create('/'));

        self::assertSame($key1, $key2);
    }

    /**
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
     */
    public function testResolvesKeyWithAuthIdentifierIfAuthenticated(): void
    {
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();
        $strategy = new BaseStrategy(
            new class ($app) extends AuthManager {
                public function check(): bool
                {
                    return true;
                }

                public function id(): string
                {
                    return '49f91eb0-180f-46d2-899d-2046ad1ddda3';
                }
            },
        );
        $key = $strategy->key(Request::create('/'));

        self::assertSame('79d8c3591a0419c04e74600696090ee7', $key);
    }

    /**
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
     */
    public function testResolvesKeyWithIntegerAuthIdentifierIfAuthenticated(): void
    {
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();
        $strategy = new BaseStrategy(
            new class ($app) extends AuthManager {
                public function check(): bool
                {
                    return true;
                }

                public function id(): int
                {
                    return 42;
                }
            },
        );
        $key = $strategy->key(Request::create('/'));

        self::assertSame('9350d47085691dcd1698e672fb894b45', $key);
    }
}
