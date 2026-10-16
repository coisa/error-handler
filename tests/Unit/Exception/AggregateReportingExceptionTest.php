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

namespace CoiSA\ErrorHandler\Test\Unit\Exception;

use CoiSA\ErrorHandler\Exception\AggregateReportingException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies original cause identity and the ordered, nonempty reporter failure list.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Exception\AggregateReportingException
 */
final class AggregateReportingExceptionTest extends TestCase
{
    /** Verifies a single failure retains the original as both explicit input and previous cause. */
    public function testSingleFailurePreservesCauseWithoutEmbeddingPrivateMessages(): void
    {
        $original = new \Error('private original diagnostic');
        $failure = new \RuntimeException('private reporter diagnostic');
        $exception = new AggregateReportingException($original, $failure);

        self::assertSame($original, $exception->getOriginalThrowable());
        self::assertSame($original, $exception->getPrevious());
        self::assertSame([$failure], $exception->getFailures());
        self::assertSame(0, $exception->getCode());
        self::assertSame('One or more throwable handlers failed.', $exception->getMessage());
    }

    /** Verifies failure order, repeated objects and returned-array isolation without cloning diagnostics. */
    public function testMultipleFailuresPreserveOrderAndIdentity(): void
    {
        $original = new \LogicException('original');
        $first = new \RuntimeException('first');
        $second = new \Error('second');
        $exception = new AggregateReportingException($original, $first, $second, $first);
        $copy = $exception->getFailures();
        array_pop($copy);

        self::assertSame($original, $exception->getOriginalThrowable());
        self::assertSame([$first, $second, $first], $exception->getFailures());
        self::assertSame([$first, $second], $copy);
    }

    /** Verifies PHP 8 named variadic failures become an ordered list, also executable on PHP 7.4. */
    public function testNamedVariadicFailuresBecomeAListWithoutChangingOrder(): void
    {
        $original = new \Error('original');
        $first = new \RuntimeException('first');
        $second = new \LogicException('second');

        /**
         * Forward the native variadic keys, which PHP 8 retains for named arguments.
         *
         * PHP 7.4 call_user_func_array treats the same input as positional arguments.
         *
         * @param \Throwable $originalThrowable the unchanged diagnostic
         * @param \Throwable $failure           the first reporter failure
         * @param \Throwable ...$failures       any remaining named or positional failures
         *
         * @return AggregateReportingException the public constructor's result
         */
        $construct = static fn(
            \Throwable $originalThrowable,
            \Throwable $failure,
            \Throwable ...$failures
        ): AggregateReportingException => new AggregateReportingException($originalThrowable, $failure, ...$failures);

        $exception = \call_user_func_array($construct, [
            'originalThrowable' => $original,
            'failure' => $first,
            'additional' => $second,
            'repeated' => $first,
        ]);

        self::assertSame($original, $exception->getOriginalThrowable());
        self::assertSame([$first, $second, $first], $exception->getFailures());
    }
}
