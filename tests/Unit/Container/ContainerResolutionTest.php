<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Unit\Container;

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Exception\ContainerException;
use CoiSA\ErrorHandler\Container\Exception\NotFoundException;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Verifies parent precedence, local caching, aliases and exact exception chaining.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Container\ErrorHandlerContainer
 * @covers \CoiSA\ErrorHandler\Container\Exception\ContainerException
 * @covers \CoiSA\ErrorHandler\Container\Exception\NotFoundException
 */
final class ContainerResolutionTest extends TestCase
{
    /** Verifies a parent-owned value takes precedence over a known library service. */
    public function testParentValueTakesPrecedenceWithoutLocalCaching(): void
    {
        $first = new \stdClass();
        $second = new \stdClass();
        $parent = $this->createMock(ContainerInterface::class);
        $parent->method('has')->with(ErrorHandler::class)->willReturn(true);
        $parent->expects(self::exactly(2))->method('get')->with(ErrorHandler::class)
            ->willReturnOnConsecutiveCalls($first, $second)
        ;
        $container = new ErrorHandlerContainer($parent);
        self::assertTrue($container->has(ErrorHandler::class));
        self::assertSame($first, $container->get(ErrorHandler::class));
        self::assertSame($second, $container->get(ErrorHandler::class));
    }

    /** Verifies local services are shared, including aliases to the same aggregate. */
    public function testLocalInstancesAndAliasesShareTheSameService(): void
    {
        $container = new ErrorHandlerContainer();
        self::assertFalse($container->has('missing fixture'));
        $aggregate = $container->get(ThrowableHandlerAggregate::class);
        self::assertInstanceOf(ThrowableHandlerAggregate::class, $aggregate);
        self::assertSame($aggregate, $container->get(ThrowableHandlerAggregate::class));
        self::assertSame($aggregate, $container->get(ThrowableHandlerInterface::class));
        $handler = $container->get(ErrorHandler::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);
        self::assertSame($handler, $container->get(ErrorHandler::class));
    }

    /** Verifies the unknown identifier survives in the not-found diagnostic. */
    public function testMissingLocalServiceNamesTheRequestedIdentifier(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Factory for class fixture.missing was not found');
        (new ErrorHandlerContainer())->get('fixture.missing');
    }

    /** Verifies an internal factory failure is wrapped with exact cause, message and code. */
    public function testFactoryFailurePreservesOriginalCause(): void
    {
        $failure = new \RuntimeException('synthetic dependency failure', 17);
        $parent = $this->createMock(ContainerInterface::class);
        $parent->method('has')->willReturnCallback(
            /**
             * Selects the single dependency owned by the synthetic parent.
             *
             * @param string $id the requested identifier
             *
             * @return bool whether the fixture supplies this dependency
             */
            static fn(string $id): bool => ThrowableHandlerInterface::class === $id,
        );
        $parent->method('get')->with(ThrowableHandlerInterface::class)->willThrowException($failure);

        try {
            (new ErrorHandlerContainer($parent))->get(ErrorHandler::class);
            self::fail('The failed dependency must propagate as a container exception.');
        } catch (ContainerException $caught) {
            self::assertSame($failure, $caught->getPrevious());
            self::assertSame('synthetic dependency failure', $caught->getMessage());
            self::assertSame(17, $caught->getCode());
        }
    }

    /** Verifies a delegated not-found failure retains its original identity. */
    public function testInternalNotFoundExceptionIsNotRewrapped(): void
    {
        $failure = new NotFoundException('synthetic missing dependency');
        $parent = $this->createMock(ContainerInterface::class);
        $parent->method('has')->willReturnCallback(
            /**
             * Selects the single dependency owned by the synthetic parent.
             *
             * @param string $id the requested identifier
             *
             * @return bool whether the fixture supplies this dependency
             */
            static fn(string $id): bool => ThrowableHandlerInterface::class === $id,
        );
        $parent->method('get')->with(ThrowableHandlerInterface::class)->willThrowException($failure);

        try {
            (new ErrorHandlerContainer($parent))->get(ErrorHandler::class);
            self::fail('The dependency not-found exception must propagate.');
        } catch (NotFoundException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Verifies both public wrapper factories preserve their exact Throwable cause. */
    public function testExceptionFactoriesPreserveDiagnosticMetadata(): void
    {
        $cause = new \Exception('original cause', 42);
        foreach ([ContainerException::class, NotFoundException::class] as $class) {
            $exception = $class::createFromThrowable($cause);
            self::assertInstanceOf($class, $exception);
            self::assertSame($cause, $exception->getPrevious());
            self::assertSame('original cause', $exception->getMessage());
            self::assertSame(42, $exception->getCode());
        }
    }
}
