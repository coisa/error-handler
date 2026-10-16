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

namespace CoiSA\ErrorHandler\Test\Unit;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

/**
 * Tests direct delegation and the real PHP callback stack in disposable PHPUnit processes.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\ErrorHandler
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
final class ErrorHandlerTest extends TestCase
{
    /** Verifies registration is explicit, idempotent and restores both previous callbacks. */
    public function testRegistrationIsExplicitIdempotentAndRestoresPreviousCallbacks(): void
    {
        /** @return bool confirms the fixture owns the previous PHP error callback */
        $previousError = static fn(): bool => true;

        /**
         * Supplies a previous exception callback without executing application code.
         *
         * @param \Throwable $failure the previous callback's input
         *
         * @throws \LogicException if an unexpected native exception reaches the sentinel
         */
        $previousException = static function (\Throwable $failure): void {
            throw new \LogicException('Unexpected previous exception callback.', 0, $failure);
        };
        set_error_handler($previousError);
        set_exception_handler($previousException);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::never())->method('handleShutdown');
        $handler = new ErrorHandler($reporter, null, $shutdown);

        try {
            self::assertSame($previousError, set_error_handler($previousError));
            restore_error_handler();
            self::assertSame($previousException, set_exception_handler($previousException));
            restore_exception_handler();
            $handler->unregister();
            $handler->register();
            $handler->register();
            self::assertSame([$handler, 'handlePhpError'], set_error_handler($previousError));
            restore_error_handler();
            self::assertSame([$handler, 'handleThrowable'], set_exception_handler($previousException));
            restore_exception_handler();
            $handler->unregister();
            $handler->unregister();
            self::assertSame($previousError, set_error_handler($previousError));
            restore_error_handler();
            self::assertSame($previousException, set_exception_handler($previousException));
            restore_exception_handler();
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /** Verifies direct reporting retains the supplied event and propagates reporter failures once. */
    public function testDirectReportingPreservesIdentityAndPropagatesFailure(): void
    {
        $original = new \Error('incoming');
        $failure = new \RuntimeException('reporter');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original))
            ->willThrowException($failure)
        ;

        try {
            (new ErrorHandler($reporter))->handleThrowable($original);
            self::fail('The reporter failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Verifies a returning PHP adapter receives exact metadata without reporting another event. */
    public function testPhpAdapterCanReturnWithoutReporting(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $adapter = $this->createMock(PhpErrorHandlerInterface::class);
        $adapter->expects(self::once())->method('handlePhpError')->with(E_WARNING, 'warning', '/fixture.php', 42);
        (new ErrorHandler($reporter, $adapter))->handlePhpError(E_WARNING, 'warning', '/fixture.php', 42);
    }

    /** Verifies only ErrorException adapter failures are delivered to the reporter. */
    public function testPhpAdapterErrorExceptionIsReportedWithOriginalIdentity(): void
    {
        $failure = new \ErrorException('adapter', 0, E_WARNING);
        $adapter = $this->createMock(PhpErrorHandlerInterface::class);
        $adapter->method('handlePhpError')->willThrowException($failure);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($failure));
        (new ErrorHandler($reporter, $adapter))->handlePhpError(E_WARNING, 'warning', '/fixture.php', 42);
    }

    /** Verifies an unrelated PHP adapter failure propagates without being reported again. */
    public function testOtherPhpAdapterFailuresPropagate(): void
    {
        $failure = new \LogicException('adapter');
        $adapter = $this->createMock(PhpErrorHandlerInterface::class);
        $adapter->method('handlePhpError')->willThrowException($failure);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');

        try {
            (new ErrorHandler($reporter, $adapter))->handlePhpError(E_WARNING, 'warning', '/fixture.php', 42);
            self::fail('The unrelated adapter failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Verifies registered shutdown delegation and its original failure are reported once. */
    public function testRegisteredShutdownReportsAdapterFailure(): void
    {
        $failure = new \Error('shutdown adapter');
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::once())->method('handleShutdown')->willThrowException($failure);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($failure));
        $handler = new ErrorHandler($reporter, null, $shutdown);
        $handler->register();

        try {
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
        }
    }

    /** Verifies a successful explicit shutdown call does not report a synthetic failure. */
    public function testRegisteredShutdownCanReturnNormally(): void
    {
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::once())->method('handleShutdown');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $handler = new ErrorHandler($reporter, null, $shutdown);
        $handler->register();

        try {
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
        }
    }

    /** Verifies actual native warning delivery, masking and @ suppression without terminating PHP. */
    public function testNativeWarningsAndSuppression(): void
    {
        $events = [];
        $reporter = new CallableThrowableHandler(
            /**
             * Retains actual converted warning events for metadata assertions.
             *
             * @param \Throwable $event the native warning converted by the default adapter
             */
            static function (\Throwable $event) use (&$events): void {
                $events[] = $event;
            },
        );
        $handler = new ErrorHandler($reporter);
        $mask = error_reporting(E_ALL);
        $handler->register();

        try {
            $line = __LINE__ + 1;
            trigger_error('reported fixture warning', E_USER_WARNING);
            @trigger_error('suppressed fixture warning', E_USER_WARNING);
            error_reporting(E_ALL & ~E_USER_WARNING);
            trigger_error('masked fixture warning', E_USER_WARNING);
            error_reporting(E_ALL);
            fopen(__DIR__ . '/does-not-exist.fixture', 'r');
            self::assertCount(2, $events);
            self::assertInstanceOf(\ErrorException::class, $events[0]);
            self::assertSame(E_USER_WARNING, $events[0]->getSeverity());
            self::assertSame('reported fixture warning', $events[0]->getMessage());
            self::assertSame(__FILE__, $events[0]->getFile());
            self::assertSame($line, $events[0]->getLine());
            self::assertInstanceOf(\ErrorException::class, $events[1]);
            self::assertSame(E_WARNING, $events[1]->getSeverity());
        } finally {
            $handler->unregister();
            error_reporting($mask);
        }
    }
}
