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

use CoiSA\ErrorHandler\Handler\FilteringThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Verifies selection, strict boolean results and unchanged collaborator failures.
 *
 * @internal
 */
#[CoversClass(FilteringThrowableHandler::class)]
#[UsesClass(ThrowableHandlerAggregate::class)]
final class FilteringThrowableHandlerTest extends TestCase
{
    /**
     * Evaluate once per call, including repeated delivery of the same input object.
     *
     * @param bool $match the explicit inclusion decision
     */
    #[DataProvider('provideSelectionIsPerInvocationAndPreservesIdentityCases')]
    public function testSelectionIsPerInvocationAndPreservesIdentity(bool $match): void
    {
        $original = new \RuntimeException('Synthetic repeated input');
        $delegate = $this->createMock(ThrowableHandlerInterface::class);
        $delegate->expects(self::exactly($match ? 2 : 0))
            ->method('handleThrowable')->with(self::identicalTo($original))
        ;
        $predicateCalls = 0;
        $handler = new FilteringThrowableHandler(
            $delegate,
            /**
             * Inspect the original object and return an explicit boolean.
             *
             * @param \Throwable $received the predicate input
             *
             * @return bool the selected decision for both calls
             */
            static function (\Throwable $received) use ($original, $match, &$predicateCalls): bool {
                self::assertSame($original, $received);
                ++$predicateCalls;

                return $match;
            },
        );

        $this->expectOutputString('');
        $handler->handleThrowable($original);
        $handler->handleThrowable($original);

        self::assertSame(2, $predicateCalls);
    }

    /** @return array<string, array{bool}> independent match and nonmatch decisions */
    public static function provideSelectionIsPerInvocationAndPreservesIdentityCases(): iterable
    {
        return ['match' => [true], 'nonmatch' => [false]];
    }

    /**
     * Reject truthy and false-like values instead of coercing application policy.
     *
     * @param mixed $result the deliberately malformed predicate result
     */
    #[DataProvider('provideRejectsNonBooleanResultBeforeDeliveryCases')]
    public function testRejectsNonBooleanResultBeforeDelivery($result): void
    {
        $delegate = $this->createMock(ThrowableHandlerInterface::class);
        $delegate->expects(self::never())->method('handleThrowable');

        /** @return mixed the deliberately invalid result used to test the runtime boundary */
        $predicate = static fn(\Throwable $received) => $result;

        /**
         * Deliberately supply a non-boolean callback to verify the handler's strict runtime boundary.
         *
         * @phpstan-ignore argument.type (Negative input intentionally violates the documented predicate contract.)
         */
        $handler = new FilteringThrowableHandler($delegate, $predicate);

        $this->expectException(\TypeError::class);
        $handler->handleThrowable(new \RuntimeException('Synthetic invalid predicate'));
    }

    /** @return array<string, array{mixed}> non-boolean values on both sides of PHP truthiness */
    public static function provideRejectsNonBooleanResultBeforeDeliveryCases(): iterable
    {
        return ['one' => [1], 'zero' => [0], 'string' => ['yes'], 'empty array' => [[]], 'null' => [null]];
    }

    /**
     * Propagate the same failure once without invoking or retrying another route.
     *
     * @param bool                     $predicateFails whether selection or delivery raises the failure
     * @param class-string<\Throwable> $failureClass   the collaborator's failure type
     */
    #[DataProvider('provideCollaboratorFailuresPropagateUnchangedCases')]
    public function testCollaboratorFailuresPropagateUnchanged(bool $predicateFails, string $failureClass): void
    {
        $failure = new $failureClass('Synthetic collaborator failure');
        $original = new \RuntimeException('Synthetic reported failure');
        $delegate = $this->createMock(ThrowableHandlerInterface::class);
        $delegate->expects($predicateFails ? self::never() : self::once())
            ->method('handleThrowable')->with(self::identicalTo($original))->willThrowException($failure)
        ;
        $predicateCalls = 0;
        $handler = new FilteringThrowableHandler(
            $delegate,
            /**
             * Fail selection or allow the delegate to fail.
             *
             * @param \Throwable $received the unchanged input
             *
             * @return bool true when delivery should be attempted
             *
             * @throws \Throwable the exact configured predicate failure
             */
            static function (\Throwable $received) use ($original, $predicateFails, $failure, &$predicateCalls): bool {
                self::assertSame($original, $received);
                ++$predicateCalls;
                if ($predicateFails) {
                    throw $failure;
                }

                return true;
            },
        );

        $caught = null;

        try {
            $handler->handleThrowable($original);
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertSame($failure, $caught);
        self::assertSame(1, $predicateCalls);
    }

    /** @return array<string, array{bool, class-string<\Throwable>}> failure types without process-serialized traces */
    public static function provideCollaboratorFailuresPropagateUnchangedCases(): iterable
    {
        return [
            'predicate exception' => [true, \RuntimeException::class],
            'predicate error' => [true, \Error::class],
            'delegate exception' => [false, \RuntimeException::class],
            'delegate error' => [false, \Error::class],
        ];
    }

    /** A nonmatch skips only the selected branch and leaves later aggregate deliveries intact. */
    public function testNonmatchDoesNotStopLaterAggregateReporters(): void
    {
        $original = new \RuntimeException('Synthetic aggregate input');
        $skipped = $this->createMock(ThrowableHandlerInterface::class);
        $skipped->expects(self::never())->method('handleThrowable');
        $later = $this->createMock(ThrowableHandlerInterface::class);
        $later->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original));
        $aggregate = new ThrowableHandlerAggregate(
            new FilteringThrowableHandler(
                $skipped,
                /** @return bool exclude the diagnostic branch for this synthetic input */
                static fn(\Throwable $received): bool => false,
            ),
            $later,
        );

        $aggregate->handleThrowable($original);
    }
}
