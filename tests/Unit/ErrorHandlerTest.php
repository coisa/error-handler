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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;

/**
 * Tests direct delegation and the real PHP callback stack in disposable PHPUnit processes.
 *
 * @internal
 */
#[CoversClass(ErrorHandler::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
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
        $reporter->expects(self::never())->method('handleThrowable');
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::never())->method('handleShutdown');
        $handler = new ErrorHandler($reporter, null, $shutdown);

        try {
            self::assertSame([$previousError, $previousException], self::callbacks());
            $handler->unregister();
            $handler->register();
            [$errorCallback, $exceptionCallback] = self::callbacks();
            self::assertInstanceOf(\Closure::class, $errorCallback);
            self::assertInstanceOf(\Closure::class, $exceptionCallback);
            self::assertNotSame($previousError, $errorCallback);
            self::assertNotSame($previousException, $exceptionCallback);
            $handler->register();
            self::assertSame([$errorCallback, $exceptionCallback], self::callbacks());
            $handler->unregister();
            $handler->unregister();
            self::assertSame([$previousError, $previousException], self::callbacks());
            $handler->register();
            self::assertSame([$errorCallback, $exceptionCallback], self::callbacks());
            $handler->unregister();
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /** Verifies an out-of-order unregister leaves the inner scope active until proper cleanup. */
    public function testUnregisterRequiresReverseRegistrationOrder(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $outer = new ErrorHandler($reporter);
        $inner = new ErrorHandler($reporter);
        $before = self::callbacks();
        $outer->register();
        $inner->register();

        try {
            $activeInner = self::callbacks();

            try {
                $outer->unregister();
                self::fail('An outer scope must not remove the active inner callbacks.');
            } catch (\LogicException) {
                self::assertSame($activeInner, self::callbacks());
            }
        } finally {
            $inner->unregister();
            $outer->unregister();
        }

        self::assertSame($before, self::callbacks());
    }

    /**
     * Reject partial ownership loss without changing callback identities or their native masks.
     *
     * @param bool $replaceError whether the foreign integration replaces only the error callback
     */
    #[DataProvider('providePartialCallbackReplacementPreservesOwnershipAndMasksCases')]
    #[WithoutErrorHandler]
    public function testPartialCallbackReplacementPreservesOwnershipAndMasks(bool $replaceError): void
    {
        $before = self::callbacks();
        $mask = error_reporting(E_ALL & ~E_USER_WARNING);
        $previousEvents = [];
        $foreignEvents = [];
        $ownedEvents = [];
        set_error_handler(
            /**
             * Observe delivery through the original callback's restricted mask.
             *
             * @param int $code the delivered PHP severity
             *
             * @return bool suppress the native diagnostic after recording it
             */
            static function (int $code) use (&$previousEvents): bool {
                $previousEvents[] = $code;

                return true;
            },
            E_USER_NOTICE,
        );
        set_exception_handler(
            /** @param \Throwable $error an unused input on the original exception callback */
            static function (\Throwable $error): void {
            },
        );
        $previous = self::callbacks();
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::never())->method('handleShutdown');
        $handler = new ErrorHandler(
            $reporter,
            /** @param int $code the severity delivered through the handler's unrestricted native mask */
            static function (int $code) use (&$ownedEvents): void {
                $ownedEvents[] = $code;
            },
            $shutdown,
        );
        $handler->register();

        if ($replaceError) {
            set_error_handler(
                /**
                 * Record the foreign callback without accepting severities outside its own mask.
                 *
                 * @param int $code the delivered PHP severity
                 *
                 * @return bool suppress the native diagnostic after recording it
                 */
                static function (int $code) use (&$foreignEvents): bool {
                    $foreignEvents[] = $code;

                    return true;
                },
                E_USER_NOTICE,
            );
        } else {
            set_exception_handler(
                /** @param \Throwable $error an unused input on the foreign exception callback */
                static function (\Throwable $error): void {
                },
            );
        }

        try {
            try {
                $foreign = self::callbacks();

                try {
                    $handler->unregister();
                    self::fail('Losing either callback must prevent unregistering the active scope.');
                } catch (\LogicException) {
                    self::assertSame($foreign, self::callbacks());
                }
                $handler->handleShutdown();
                self::assertSame($foreign, self::callbacks());
                self::assertSame(E_ALL & ~E_USER_WARNING, error_reporting());
                trigger_error('Observe current notice mask', E_USER_NOTICE);
                trigger_error('Observe current warning mask', E_USER_WARNING);
                self::assertSame($replaceError ? [E_USER_NOTICE] : [], $foreignEvents);
                self::assertSame($replaceError ? [] : [E_USER_NOTICE, E_USER_WARNING], $ownedEvents);
                self::assertSame([], $previousEvents);
            } finally {
                if ($replaceError) {
                    restore_error_handler();
                } else {
                    restore_exception_handler();
                }
                $handler->unregister();
            }

            self::assertSame($previous, self::callbacks());
            trigger_error('Observe restored notice mask', E_USER_NOTICE);
            trigger_error('Observe restored warning mask', E_USER_WARNING);
            self::assertSame([E_USER_NOTICE], $previousEvents);
        } finally {
            restore_error_handler();
            restore_exception_handler();
            error_reporting($mask);
        }

        self::assertSame($before, self::callbacks());
    }

    /** @return array<string, array{bool}> independent replacement of each native callback */
    public static function providePartialCallbackReplacementPreservesOwnershipAndMasksCases(): iterable
    {
        return ['only error callback' => [true], 'only exception callback' => [false]];
    }

    /** Verifies a poisoned registration skips shutdown and resets only after a completed scope. */
    public function testFailedRegistrationSkipsShutdownAndCanStartANewScope(): void
    {
        $first = new \Error('first delivery');
        $retry = new \RuntimeException('suppressed retry');
        $afterReset = new \RuntimeException('new scope');
        $failure = new \LogicException('reporter failed');
        $received = [];
        $reporter = new CallableThrowableHandler(
            /**
             * Records original deliveries and fails only for the first registration.
             *
             * @param \Throwable $error the event delivered by the handler
             *
             * @throws \LogicException the first reporter failure
             */
            static function (\Throwable $error) use (&$received, $failure): void {
                $received[] = $error;
                if (1 === \count($received)) {
                    throw $failure;
                }
            },
        );
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::never())->method('handleShutdown');
        $handler = new ErrorHandler($reporter, null, $shutdown);
        $handler->register();

        try {
            try {
                $handler->handleThrowable($first);
                self::fail('The first reporter failure must propagate unchanged.');
            } catch (\LogicException $caught) {
                self::assertSame($failure, $caught);
            }

            $handler->register();

            try {
                $handler->handleThrowable($retry);
                self::fail('Idempotent register must not retry a failed reporter.');
            } catch (\RuntimeException $caught) {
                self::assertSame($retry, $caught);
            }
            $handler->handleShutdown();
            self::assertSame([$first], $received);

            $handler->unregister();
            $handler->register();
            $handler->handleThrowable($afterReset);
            self::assertSame([$first, $afterReset], $received);
        } finally {
            $handler->unregister();
        }
    }

    /** Verifies recursion is rejected before nested delivery and the direct-call guard is released. */
    public function testRecursiveDeliveryIsRejectedWithoutPoisoningAnIndependentCall(): void
    {
        $nested = new \RuntimeException('nested delivery');
        $first = new \Error('outer delivery');
        $independent = new \RuntimeException('independent delivery');
        $received = [];
        $scope = new class {
            /** @var null|ErrorHandler The handler linked after its reporter is constructed. */
            public ?ErrorHandler $handler = null;
        };
        $reporter = new CallableThrowableHandler(
            /**
             * Attempts one recursive delivery through the same configured handler.
             *
             * @param \Throwable $error the original event received by this reporter
             *
             * @throws \LogicException when the handler rejects recursive delivery
             */
            static function (\Throwable $error) use (&$received, $scope, $nested): void {
                $received[] = $error;
                if (1 === \count($received)) {
                    $handler = $scope->handler;
                    if (!$handler instanceof ErrorHandler) {
                        throw new \LogicException('The recursive fixture must be configured before delivery.');
                    }
                    $handler->handleThrowable($nested);
                }
            },
        );
        $handler = new ErrorHandler($reporter);
        $scope->handler = $handler;

        try {
            $handler->handleThrowable($first);
            self::fail('A recursive delivery must be rejected.');
        } catch (\LogicException $caught) {
            self::assertSame($nested, $caught->getPrevious());
        }

        self::assertSame([$first], $received);
        $handler->handleThrowable($independent);
        self::assertSame([$first, $independent], $received);
    }

    /** Verifies the installed uncaught callback preserves reporter failure and restores its scope. */
    public function testInstalledUncaughtCallbackPropagatesReporterFailureOnce(): void
    {
        $original = new \Error('uncaught input');
        $failure = new \RuntimeException('uncaught reporter failed');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original))
            ->willThrowException($failure)
        ;
        $handler = new ErrorHandler($reporter);
        $before = self::callbacks();
        $handler->register();

        try {
            $callback = set_exception_handler(
                /**
                 * Temporarily exposes the installed callback without dispatching a native failure.
                 *
                 * @param \Throwable $error the unused probe input
                 */
                static function (\Throwable $error): void {
                },
            );
            restore_exception_handler();
            self::assertInstanceOf(\Closure::class, $callback);

            try {
                $callback($original);
                self::fail('The installed callback must propagate its reporter failure.');
            } catch (\RuntimeException $caught) {
                self::assertSame($failure, $caught);
            }
        } finally {
            $handler->unregister();
        }

        self::assertSame($before, self::callbacks());
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
        $adapter->expects(self::once())->method('handlePhpError')->willThrowException($failure);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($failure));
        (new ErrorHandler($reporter, $adapter))->handlePhpError(E_WARNING, 'warning', '/fixture.php', 42);
    }

    /** Verifies an unrelated PHP adapter failure propagates without being reported again. */
    public function testOtherPhpAdapterFailuresPropagate(): void
    {
        $failure = new \LogicException('adapter');
        $adapter = $this->createMock(PhpErrorHandlerInterface::class);
        $adapter->expects(self::once())->method('handlePhpError')->willThrowException($failure);
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
    #[WithoutErrorHandler]
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

    /**
     * Snapshots both callback identities without retaining a probe on either native stack.
     *
     * @return array{null|callable, null|callable} the current PHP error and exception callbacks
     *
     * @phpstan-impure
     */
    private static function callbacks(): array
    {
        /** @return bool the temporary error callback used only to inspect the active callback */
        $error = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $exception = set_exception_handler(
            /**
             * Supplies a temporary exception callback without handling an actual failure.
             *
             * @param \Throwable $error the unused probe input
             */
            static function (\Throwable $error): void {
            },
        );
        restore_exception_handler();

        return [$error, $exception];
    }
}
