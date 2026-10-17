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
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallablePhpErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Verifies constructor normalization without changing reporting or lifecycle policy.
 *
 * @internal
 */
#[CoversClass(ErrorHandler::class)]
#[UsesClass(CallableThrowableHandler::class)]
#[UsesClass(CallablePhpErrorHandler::class)]
#[UsesClass(CallableShutdownHandler::class)]
#[UsesClass(ErrorException::class)]
#[UsesClass(ThrowErrorExceptionPhpErrorHandler::class)]
#[UsesClass(PhpLastErrorShutdownHandler::class)]
final class ErrorHandlerConstructorTest extends TestCase
{
    /**
     * Normalizes three closures or callable arrays without executing them early.
     *
     * @param bool $arrays whether each callback is supplied as an object/method pair
     */
    #[DataProvider('provideThreeClosuresAreInertAndKeepTheirDelegationContractsCases')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testThreeClosuresAreInertAndKeepTheirDelegationContracts(bool $arrays): void
    {
        $events = [];
        $before = self::callbacks();

        /**
         * Records the original event and returns a value unrelated to diagnostic delivery.
         *
         * @param \Throwable $event the original event supplied to the reporter
         *
         * @return string a callback result the void adapter must ignore
         */
        $reporter = static function (\Throwable $event) use (&$events): string {
            $events[] = ['throwable', $event];

            return 'ignored';
        };

        /**
         * Retains the exact metadata without adding native false/fall-through semantics.
         *
         * @param int    $code     the unmodified severity
         * @param string $message  the unmodified error message
         * @param string $filename the unmodified source filename
         * @param int    $line     the unmodified source line
         *
         * @return false a callback result that the void adapter must ignore
         */
        $phpErrors = static function (int $code, string $message, string $filename, int $line) use (&$events): false {
            $events[] = ['php', $code, $message, $filename, $line];

            return false;
        };

        /** @return int a callback result unrelated to the shutdown interface */
        $shutdown = static function () use (&$events): int {
            $events[] = ['shutdown'];

            return 7;
        };
        $handler = new ErrorHandler(
            $arrays ? [$reporter, '__invoke'] : $reporter,
            $arrays ? [$phpErrors, '__invoke'] : $phpErrors,
            $arrays ? [$shutdown, '__invoke'] : $shutdown,
        );
        self::assertSame([], $events);
        self::assertSame($before, self::callbacks());
        $handler->handleShutdown();
        self::assertSame([], $events);

        $original = new \RuntimeException('Original constructor fixture');
        $handler->handleThrowable($original);
        $handler->handlePhpError(E_NOTICE, 'Exact metadata', '/synthetic.php', 23);
        $handler->register();

        try {
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
        }

        self::assertSame([
            ['throwable', $original],
            ['php', E_NOTICE, 'Exact metadata', '/synthetic.php', 23],
            ['shutdown'],
        ], $events);
        self::assertSame($before, self::callbacks());
    }

    /** @return array<string, array{bool}> native callable forms for all three inputs */
    public static function provideThreeClosuresAreInertAndKeepTheirDelegationContractsCases(): iterable
    {
        return ['closures' => [false], 'object and method pairs' => [true]];
    }

    /**
     * Omitted and explicitly null options retain the same default suppression and conversion policy.
     *
     * @param bool $explicitNulls whether to supply the two nullable defaults explicitly
     */
    #[DataProvider('provideNullableDefaultsKeepTheExistingErrorPolicyCases')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNullableDefaultsKeepTheExistingErrorPolicy(bool $explicitNulls): void
    {
        $delegate = $this->createMock(ThrowableHandlerInterface::class);
        $delegate->expects(self::once())->method('handleThrowable')->with(self::callback(
            /**
             * Checks enabled conversion while rejecting suppression or shutdown deliveries.
             *
             * @param mixed $event the reported diagnostic
             *
             * @return bool whether all original PHP metadata was preserved
             */
            static fn(mixed $event): bool => $event instanceof ErrorException
                && 'Enabled default' === $event->getMessage()
                && E_USER_WARNING === $event->getSeverity()
                && '/defaults.php' === $event->getFile()
                && 13 === $event->getLine(),
        ));
        $reporter = $delegate->handleThrowable(...);
        $handler = $explicitNulls ? new ErrorHandler($reporter, null, null) : new ErrorHandler($reporter);
        $mask = error_reporting(E_ALL);

        try {
            $handler->handlePhpError(E_USER_WARNING, 'Enabled default', '/defaults.php', 13);
            error_reporting(0);
            $handler->handlePhpError(E_USER_WARNING, 'Suppressed default', '/defaults.php', 14);
            $handler->handleShutdown();
        } finally {
            error_reporting($mask);
        }
    }

    /** @return array<string, array{bool}> omission and explicit null for the optional inputs */
    public static function provideNullableDefaultsKeepTheExistingErrorPolicyCases(): iterable
    {
        return ['omitted' => [false], 'explicit null' => [true]];
    }

    /** Supplied interfaces win over __invoke and their original mutable identity is retained. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInvokableInterfacesKeepTheirInstancesAndInterfaceMethods(): void
    {
        $delegate = new class implements ThrowableHandlerInterface, PhpErrorHandlerInterface, ShutdownHandlerInterface {
            /** @var list<array<mixed>> observations on the exact application instance */
            public array $events = [];

            /** @param \Throwable $throwable the unchanged explicitly reported event */
            public function handleThrowable(\Throwable $throwable): void
            {
                $this->events[] = ['throwable', $throwable];
            }

            /**
             * Receives the exact PHP-error metadata through its interface method.
             *
             * @param int    $code     the selected severity
             * @param string $message  the selected error message
             * @param string $filename the selected source filename
             * @param int    $line     the selected source line
             */
            public function handlePhpError(int $code, string $message, string $filename, int $line): void
            {
                $this->events[] = ['php', $code, $message, $filename, $line];
            }

            /** Records an explicit shutdown notification on the same instance. */
            public function handleShutdown(): void
            {
                $this->events[] = ['shutdown'];
            }

            /** @param mixed ...$arguments inputs that must never take precedence over the interfaces */
            public function __invoke(mixed ...$arguments): void
            {
                $this->events[] = ['invoked', $arguments];
            }
        };
        $handler = new ErrorHandler($delegate, $delegate, $delegate);
        self::assertSame([], $delegate->events);
        $original = new \RuntimeException('Interface identity');
        $handler->handleThrowable($original);
        $handler->handlePhpError(E_WARNING, 'Interface policy', '/interface.php', 41);
        $handler->register();

        try {
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
        }

        self::assertSame([
            ['throwable', $original],
            ['php', E_WARNING, 'Interface policy', '/interface.php', 41],
            ['shutdown'],
        ], $delegate->events);
    }

    /** Callable reporters retain original identity and propagate the identical failure without retry. */
    public function testCallableReporterFailurePropagatesUnchanged(): void
    {
        $original = new \RuntimeException('Original failure');
        $failure = new \LogicException('Reporter failure');
        $received = [];
        $handler = new ErrorHandler(
            /** @throws \LogicException the exact private reporter failure */
            static function (\Throwable $event) use (&$received, $failure): void {
                $received[] = $event;

                throw $failure;
            },
        );

        try {
            $handler->handleThrowable($original);
            self::fail('Expected the reporter failure.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame([$original], $received);
    }

    /** A valid function name is accepted without invoking it to preflight its incompatible signature. */
    public function testCallbackSignatureErrorsAriseOnlyWhenTheCallbackIsInvoked(): void
    {
        // @phpstan-ignore argument.type (Deliberately defer the callable's invalid input contract to PHP.)
        $handler = new ErrorHandler('strlen');

        $this->expectException(\TypeError::class);
        $handler->handleThrowable(new \RuntimeException('Not a string callback argument'));
    }

    /** PHP-error callables preserve the existing ErrorException-routing versus other-failure policy. */
    public function testCallablePhpFailuresPreserveConversionAndFailureIdentity(): void
    {
        $converted = new \ErrorException('Converted error', 0, E_WARNING);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($converted));
        $handler = new ErrorHandler(
            $reporter,
            /** @throws \ErrorException the application-converted error */
            static function () use ($converted): never {
                throw $converted;
            },
        );
        $handler->handlePhpError(E_WARNING, 'Original metadata', '/failure.php', 17);
        $failure = new \LogicException('PHP callback failed');
        $handler = new ErrorHandler(
            $reporter,
            /** @throws \LogicException an unrelated failure that must not be reported again */
            static function () use ($failure): never {
                throw $failure;
            },
        );

        try {
            $handler->handlePhpError(E_WARNING, 'Original metadata', '/failure.php', 17);
            self::fail('Expected the unrelated PHP callback failure.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** A callable shutdown failure reaches the reporter once with its original identity. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCallableShutdownFailureIsReportedUnchanged(): void
    {
        $failure = new \LogicException('Shutdown callback failed');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($failure));
        $handler = new ErrorHandler(
            $reporter,
            null,
            /** @throws \LogicException the original callback failure to report */
            static function () use ($failure): never {
                throw $failure;
            },
        );
        $handler->handleShutdown();
        $handler->register();

        try {
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
        }
    }

    /**
     * Native validation rejects unsupported inputs before normalization or execution.
     *
     * @param int   $position the constructor argument to violate deliberately
     * @param mixed $input    an invalid native argument for that position
     */
    #[DataProvider('provideInvalidInputsAreRejectedCases')]
    public function testInvalidInputsAreRejected(int $position, mixed $input): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $this->expectException(\TypeError::class);

        if (0 === $position) {
            // @phpstan-ignore argument.type (The test deliberately violates the public native parameter.)
            $handler = new ErrorHandler($input);
        } elseif (1 === $position) {
            // @phpstan-ignore argument.type (The test deliberately violates the public native parameter.)
            $handler = new ErrorHandler($reporter, $input);
        } else {
            // @phpstan-ignore argument.type (The test deliberately violates the public native parameter.)
            $handler = new ErrorHandler($reporter, null, $input);
        }

        self::fail('Unexpectedly constructed ' . $handler::class);
    }

    /** @return iterable<string, array{int, mixed}> invalid native inputs for each constructor position */
    public static function provideInvalidInputsAreRejectedCases(): iterable
    {
        $inputs = [
            'false' => false,
            'integer' => 1,
            'unknown function' => 'missing_error_handler_callback',
            'object' => new \stdClass(),
        ];
        foreach (['reporter', 'PHP errors', 'shutdown'] as $position => $label) {
            foreach ($inputs as $name => $input) {
                yield $label . ' ' . $name => [$position, $input];
            }
        }

        yield 'required reporter null' => [0, null];
    }

    /**
     * Snapshots callback identity while restoring the temporary native probes immediately.
     *
     * @return array{null|callable, null|callable} the surrounding error and exception callbacks
     */
    private static function callbacks(): array
    {
        /** @return bool leave native diagnostic handling available to the caller */
        $error = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $exception = set_exception_handler(
            /** @param \Throwable $error the unused temporary probe input */
            static function (\Throwable $error): void {
            },
        );
        restore_exception_handler();

        return [$error, $exception];
    }
}
