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

namespace Matchory\ResponseCache\Tests\Support;

use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;
use Matchory\ResponseCache\Support\BaseStrategy;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\CannotUseAddMethodsException;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsEnumerationException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\ClassIsReadonlyException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\IncompatibleReturnValueException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\MethodCannotBeConfiguredException;
use PHPUnit\Framework\MockObject\MethodNameAlreadyConfiguredException;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\MockObject\UnknownTypeException;
use PHPUnit\Framework\TestCase;

class BaseStrategyTest extends TestCase
{
    /**
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
     * @throws ExpectationFailedException
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
     * @throws ExpectationFailedException
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
     * @throws ExpectationFailedException
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
     * @throws ClassAlreadyExistsException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws IncompatibleReturnValueException
     * @throws MethodCannotBeConfiguredException
     * @throws MethodNameAlreadyConfiguredException
     * @throws CannotUseAddMethodsException
     */
    public function testResolvesKeyWithAuthIdentifierIfAuthenticated(): void
    {
        $auth = $this
            ->getMockBuilder(AuthManager::class)
            ->addMethods(['check', 'id'])
            ->disableOriginalConstructor()
            ->getMock();
        $auth->expects($this->once())
             ->method('check')
             ->willReturn(true);
        $auth->expects($this->once())
             ->method('id')
             ->willReturn('49f91eb0-180f-46d2-899d-2046ad1ddda3');
        $strategy = new BaseStrategy($auth);
        $key = $strategy->key(Request::create('/'));

        self::assertSame('79d8c3591a0419c04e74600696090ee7', $key);
    }

    /**
     * @throws ClassAlreadyExistsException
     * @throws ClassIsEnumerationException
     * @throws ClassIsFinalException
     * @throws ClassIsReadonlyException
     * @throws DuplicateMethodException
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     * @throws InvalidMethodNameException
     * @throws OriginalConstructorInvocationRequiredException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws UnknownTypeException
     * @throws IncompatibleReturnValueException
     * @throws MethodCannotBeConfiguredException
     * @throws MethodNameAlreadyConfiguredException
     * @throws CannotUseAddMethodsException
     */
    public function testResolvesKeyWithIntegerAuthIdentifierIfAuthenticated(): void
    {
        $auth = $this
            ->getMockBuilder(AuthManager::class)
            ->addMethods(['check', 'id'])
            ->disableOriginalConstructor()
            ->getMock();
        $auth->expects($this->once())
             ->method('check')
             ->willReturn(true);
        $auth->expects($this->once())
             ->method('id')
             ->willReturn(42);
        $strategy = new BaseStrategy($auth);
        $key = $strategy->key(Request::create('/'));

        self::assertSame('9350d47085691dcd1698e672fb894b45', $key);
    }
}
