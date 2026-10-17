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

namespace CoiSA\ErrorHandler\Test\Unit\ServiceProvider\Factory;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Verifies optional injection, default error policy and side-effect-free construction.
 *
 * @internal
 */
#[CoversClass(ErrorHandlerFactory::class)]
#[UsesClass(ErrorHandler::class)]
#[UsesClass(ErrorException::class)]
#[UsesClass(ThrowErrorExceptionPhpErrorHandler::class)]
#[UsesClass(PhpLastErrorShutdownHandler::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ErrorHandlerFactoryTest extends TestCase
{
    /** Verifies absent optional services retain conversion/suppression defaults without registration. */
    public function testMissingOptionalServicesKeepTheDefaultErrorPolicy(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::callback(
            /**
             * Checks the converted warning rather than an internal adapter property.
             *
             * @param mixed $error the event delivered to the reporter
             *
             * @return bool whether the default adapter preserved every supplied field
             */
            static fn(mixed $error): bool => $error instanceof ErrorException
                && E_USER_WARNING === $error->getSeverity()
                && 'factory warning' === $error->getMessage()
                && '/factory.php' === $error->getFile()
                && 42 === $error->getLine(),
        ));
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(ThrowableHandlerInterface::class)->willReturn($reporter);
        $container->expects(self::exactly(2))->method('has')->willReturnMap([
            [PhpErrorHandlerInterface::class, false],
            [ShutdownHandlerInterface::class, false],
        ]);
        $before = self::callbacks();

        $handler = (new ErrorHandlerFactory())($container);

        self::assertSame($before, self::callbacks());
        $mask = error_reporting(E_ALL);

        try {
            $handler->handlePhpError(E_USER_WARNING, 'factory warning', '/factory.php', 42);
            error_reporting(0);
            $handler->handlePhpError(E_USER_WARNING, 'masked warning', '/factory.php', 43);
            $handler->handleShutdown();
        } finally {
            error_reporting($mask);
        }
        self::assertSame($before, self::callbacks());
    }

    /** Verifies explicit reporters and optional adapters retain their own policies and identities. */
    public function testConfiguredHandlersAreInjectedWithoutRegisteringDuringConstruction(): void
    {
        $original = new \RuntimeException('direct factory delivery');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original));
        $phpErrors = $this->createMock(PhpErrorHandlerInterface::class);
        $phpErrors->expects(self::once())->method('handlePhpError')->with(E_NOTICE, 'custom', '/custom.php', 17);
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::once())->method('handleShutdown');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(3))->method('get')->willReturnMap([
            [ThrowableHandlerInterface::class, $reporter],
            [PhpErrorHandlerInterface::class, $phpErrors],
            [ShutdownHandlerInterface::class, $shutdown],
        ]);
        $container->expects(self::exactly(2))->method('has')->willReturnMap([
            [PhpErrorHandlerInterface::class, true],
            [ShutdownHandlerInterface::class, true],
        ]);
        $before = self::callbacks();

        $handler = (new ErrorHandlerFactory())($container);

        self::assertSame($before, self::callbacks());
        $handler->handleThrowable($original);
        $handler->handlePhpError(E_NOTICE, 'custom', '/custom.php', 17);
        $handler->handleShutdown();
        $handler->register();

        try {
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
        }
        self::assertSame($before, self::callbacks());
    }

    /** Verifies required reporter failure propagates before optional service discovery. */
    public function testReporterConstructionFailureIsNotReplacedWithDefaults(): void
    {
        $failure = new \LogicException('required reporter failed');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with(ThrowableHandlerInterface::class)
            ->willThrowException($failure)
        ;
        $container->expects(self::never())->method('has');

        try {
            (new ErrorHandlerFactory())($container);
            self::fail('A required reporter failure must prevent construction.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /**
     * Reads process-global callbacks while restoring both temporary probe stacks.
     *
     * @return array{null|callable, null|callable} current error and exception callbacks
     *
     * @phpstan-impure
     */
    private static function callbacks(): array
    {
        /** @return bool A temporary callback used only to inspect the active error callback. */
        $error = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $exception = set_exception_handler(
            /** @param \Throwable $error The unused exception-probe input. */
            static function (\Throwable $error): void {
            },
        );
        restore_exception_handler();

        return [$error, $exception];
    }
}
