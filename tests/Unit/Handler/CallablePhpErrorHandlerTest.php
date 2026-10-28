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

namespace CoiSA\ErrorHandler\Test\Unit\Handler;

use CoiSA\ErrorHandler\Handler\CallablePhpErrorHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verifies inert construction, exact delivery and application-owned policy/failures.
 *
 * @internal
 */
#[CoversClass(CallablePhpErrorHandler::class)]
final class CallablePhpErrorHandlerTest extends TestCase
{
    /**
     * Deliver precisely four original values once, ignoring false/null or another result.
     *
     * @param mixed $result the callback result discarded by the void interface
     */
    #[DataProvider('providePreservesFourArgumentsAndIgnoresCallbackResultCases')]
    public function testPreservesFourArgumentsAndIgnoresCallbackResult($result): void
    {
        $calls = [];
        $handler = new CallablePhpErrorHandler(
            /**
             * Observe the exact invocation, including any unexpected extra argument.
             *
             * @param int    $code     the reported severity
             * @param string $message  the diagnostic message
             * @param string $filename the source filename
             * @param int    $line     the source line
             *
             * @return mixed the deliberate ignored application result
             */
            static function (int $code, string $message, string $filename, int $line) use (&$calls, $result) {
                $calls[] = \func_get_args();

                return $result;
            },
        );
        self::assertSame([], $calls, 'Construction must not invoke the callback.');

        $handler->handlePhpError(E_USER_WARNING, 'Synthetic diagnostic', '/synthetic/input file.php', 42);

        self::assertSame([[E_USER_WARNING, 'Synthetic diagnostic', '/synthetic/input file.php', 42]], $calls);
    }

    /** @return array<string, array{mixed}> native-looking and ordinary callback results */
    public static function providePreservesFourArgumentsAndIgnoresCallbackResultCases(): iterable
    {
        return ['false' => [false], 'null' => [null], 'ordinary value' => ['ignored']];
    }

    /**
     * Preserve an Error or Exception object without conversion, retry or suppression.
     *
     * @param class-string<\Throwable> $failureClass the original callback failure type
     */
    #[DataProvider('providePropagatesTheSameCallbackFailureOnceCases')]
    public function testPropagatesTheSameCallbackFailureOnce(string $failureClass): void
    {
        $failure = new $failureClass('Synthetic callback failure');
        $calls = 0;
        $handler = new CallablePhpErrorHandler(
            /**
             * Fail exactly once through the adapter's public operation.
             *
             * @param int    $code     the reported severity
             * @param string $message  the diagnostic message
             * @param string $filename the source filename
             * @param int    $line     the source line
             *
             * @throws \Throwable the exact configured callback failure
             */
            static function (int $code, string $message, string $filename, int $line) use ($failure, &$calls): void {
                ++$calls;

                throw $failure;
            },
        );
        $caught = null;

        try {
            $handler->handlePhpError(E_USER_NOTICE, 'Synthetic input', __FILE__, __LINE__);
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertSame($failure, $caught);
        self::assertSame(1, $calls);
    }

    /** @return array<string, array{class-string<\Throwable>}> failure types created inside isolated tests */
    public static function providePropagatesTheSameCallbackFailureOnceCases(): iterable
    {
        return ['exception' => [\RuntimeException::class], 'error' => [\Error::class]];
    }

    /** A disabled mask does not prevent an explicit callback from owning its policy. */
    public function testDoesNotApplyAnAutomaticReportingMask(): void
    {
        $calls = [];
        $handler = new CallablePhpErrorHandler(
            /**
             * Record an explicit delivery even while PHP reporting is disabled.
             *
             * @param int    $code     the reported severity
             * @param string $message  the diagnostic message
             * @param string $filename the source filename
             * @param int    $line     the source line
             */
            static function (int $code, string $message, string $filename, int $line) use (&$calls): void {
                $calls[] = \func_get_args();
            },
        );
        $previous = error_reporting(0);

        try {
            $handler->handlePhpError(E_USER_WARNING, 'Synthetic disabled-level input', '/synthetic/masked.php', 17);
            self::assertSame([[E_USER_WARNING, 'Synthetic disabled-level input', '/synthetic/masked.php', 17]], $calls);
            self::assertSame(0, error_reporting());
        } finally {
            error_reporting($previous);
        }
    }
}
