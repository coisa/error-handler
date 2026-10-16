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
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
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

    /**
     * Verifies both factories preserve integer codes and retain string codes on the cause.
     *
     * @param int|string $code         the original exception code without coercion
     * @param int        $expectedCode the integer code accepted by the native wrapper
     *
     * @dataProvider provideExceptionFactoriesPreserveDiagnosticMetadataCases
     */
    public function testExceptionFactoriesPreserveDiagnosticMetadata($code, int $expectedCode): void
    {
        $previous = new \RuntimeException('Earlier synthetic failure');
        $cause = self::createThrowableWithCode($code, $previous);

        foreach ([ContainerException::class, NotFoundException::class] as $class) {
            $exception = $class::createFromThrowable($cause);
            self::assertInstanceOf($class, $exception);
            self::assertSame($cause, $exception->getPrevious());
            self::assertSame('original cause', $exception->getMessage());
            self::assertSame($expectedCode, $exception->getCode());
            self::assertSame($code, $cause->getCode());
            self::assertSame($previous, $cause->getPrevious());
        }
    }

    /**
     * Supplies native integer codes and string codes that must not be coerced.
     *
     * @return array<string, array{int|string, int}> original and expected wrapper codes
     */
    public static function provideExceptionFactoriesPreserveDiagnosticMetadataCases(): iterable
    {
        return [
            'positive integer' => [42, 42],
            'zero integer' => [0, 0],
            'negative integer' => [-17, -17],
            'alphanumeric SQLSTATE' => ['HY000', 0],
            'numeric SQLSTATE' => ['23000', 0],
            'numeric-looking HTTP status' => ['404', 0],
        ];
    }

    /** Verifies nested alias and factory failures retain the original SQLSTATE cause. */
    public function testNestedFactoryFailurePreservesStringCodeCause(): void
    {
        $failure = self::createThrowableWithCode('HY000');
        $parent = $this->createMock(ContainerInterface::class);
        $parent->method('has')->willReturnCallback(
            /**
             * Selects the optional reporter whose construction fails in the parent.
             *
             * @param string $id the requested identifier
             *
             * @return bool whether the parent advertises the reporter
             */
            static fn(string $id): bool => CallableThrowableHandler::class === $id,
        );
        $parent->expects(self::once())->method('get')->with(CallableThrowableHandler::class)
            ->willThrowException($failure)
        ;

        try {
            (new ErrorHandlerContainer($parent))->get(ErrorHandler::class);
            self::fail('The failed reporter must propagate through the container cause chain.');
        } catch (ContainerException $caught) {
            $aliasFailure = $caught->getPrevious();
            self::assertInstanceOf(ContainerException::class, $aliasFailure);
            $aggregateFailure = $aliasFailure->getPrevious();
            self::assertInstanceOf(ContainerException::class, $aggregateFailure);
            self::assertSame($failure, $aggregateFailure->getPrevious());

            foreach ([$caught, $aliasFailure, $aggregateFailure] as $wrapper) {
                self::assertSame('original cause', $wrapper->getMessage());
                self::assertSame(0, $wrapper->getCode());
            }

            self::assertSame('HY000', $failure->getCode());
        }
    }

    /**
     * Creates a driver-free native exception with the code type supplied by a PDO driver.
     *
     * @param int|string      $code     the original code, including string SQLSTATE values
     * @param null|\Throwable $previous an optional earlier failure preserved by the fixture
     *
     * @return \PDOException an exception carrying the original diagnostic metadata
     */
    private static function createThrowableWithCode($code, ?\Throwable $previous = null): \PDOException
    {
        return new class ($code, $previous) extends \PDOException {
            /**
             * Initializes metadata without requiring a database connection or driver.
             *
             * @param int|string      $code     the code a PDO driver could provide
             * @param null|\Throwable $previous the original earlier failure
             */
            public function __construct($code, ?\Throwable $previous)
            {
                parent::__construct('original cause', 0, $previous);

                $this->code = $code;
            }
        };
    }
}
