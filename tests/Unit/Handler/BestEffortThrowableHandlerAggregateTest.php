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

use CoiSA\ErrorHandler\Exception\AggregateReportingException;
use CoiSA\ErrorHandler\Handler\BestEffortThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies ordered, single-pass reporting and complete failure preservation.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Handler\BestEffortThrowableHandlerAggregate
 */
final class BestEffortThrowableHandlerAggregateTest extends TestCase
{
    /** Requires the aggregate to leave diagnostic output to its delegates. */
    protected function setUp(): void
    {
        $this->expectOutputString('');
    }

    /** Verifies construction does not invoke the configured reporter. */
    public function testConstructionIsInert(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');

        new BestEffortThrowableHandlerAggregate($reporter);
    }

    /**
     * Verifies zero, one and many successful reporters return normally in order.
     *
     * @param int $count the number of independently recorded slots
     *
     * @dataProvider provideSuccessfulSlotsReceiveTheOriginalOnceInOrderCases
     */
    public function testSuccessfulSlotsReceiveTheOriginalOnceInOrder(int $count): void
    {
        $original = new \Error('original');
        $calls = [];
        $reporters = [];
        $expected = [];

        for ($slot = 0; $slot < $count; ++$slot) {
            $expected[] = [$slot, $original];
            $reporters[] = new CallableThrowableHandler(
                /**
                 * Retains each slot and exact input for ordered identity assertions.
                 *
                 * @param \Throwable $received the unchanged diagnostic event
                 */
                static function (\Throwable $received) use (&$calls, $slot): void {
                    $calls[] = [$slot, $received];
                },
            );
        }

        (new BestEffortThrowableHandlerAggregate(...$reporters))->handleThrowable($original);

        self::assertSame($expected, $calls);
    }

    /**
     * Supplies boundary counts without sharing mutable reporters between tests.
     *
     * @return array<string, array{int}> independently configured slot counts
     */
    public static function provideSuccessfulSlotsReceiveTheOriginalOnceInOrderCases(): iterable
    {
        return ['zero' => [0], 'one' => [1], 'many' => [3]];
    }

    /**
     * Verifies every slot is attempted before any ordered aggregate failure escapes.
     *
     * @param list<bool> $failing which configured slots throw their distinct failure
     *
     * @dataProvider provideAttemptsEverySlotAndPreservesAllFailuresCases
     */
    public function testAttemptsEverySlotAndPreservesAllFailures(array $failing): void
    {
        $original = new \RuntimeException('original');
        $calls = [];
        $reporters = [];
        $expectedCalls = [];
        $expectedFailures = [];

        foreach ($failing as $slot => $fails) {
            $expectedCalls[] = [$slot, $original];
            $failure = $fails ? (1 === $slot ? new \Error('error') : new \LogicException('exception')) : null;
            if (null !== $failure) {
                $expectedFailures[] = $failure;
            }

            $reporters[] = new CallableThrowableHandler(
                /**
                 * Records the attempt before optionally failing with the supplied object.
                 *
                 * @param \Throwable $received the aggregate's original diagnostic event
                 *
                 * @throws \Throwable the slot's exact configured reporter failure
                 */
                static function (\Throwable $received) use (&$calls, $slot, $failure): void {
                    $calls[] = [$slot, $received];
                    if (null !== $failure) {
                        throw $failure;
                    }
                },
            );
        }

        try {
            (new BestEffortThrowableHandlerAggregate(...$reporters))->handleThrowable($original);
            self::fail('Any failed reporter must produce an aggregate failure after all attempts.');
        } catch (AggregateReportingException $caught) {
            self::assertSame($expectedCalls, $calls);
            self::assertSame($original, $caught->getOriginalThrowable());
            self::assertSame($original, $caught->getPrevious());
            self::assertSame($expectedFailures, $caught->getFailures());
        }
    }

    /**
     * Supplies partial success, each failure position and complete failure.
     *
     * @return array<string, array{list<bool>}> ordered synthetic slot outcomes
     */
    public static function provideAttemptsEverySlotAndPreservesAllFailuresCases(): iterable
    {
        return [
            'one failure' => [[true]],
            'first fails' => [[true, false, false]],
            'middle fails' => [[false, true, false]],
            'last fails' => [[false, false, true]],
            'all fail' => [[true, true, true]],
            'two failures with success' => [[true, false, true]],
        ];
    }

    /** Verifies duplicate slots and repeated invocations remain deliberate separate attempts. */
    public function testDuplicateSlotsAreAttemptedOncePerInvocationWithoutRetainingFailures(): void
    {
        $original = new \Error('original');
        $failure = new \LogicException('same reporter failure');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::exactly(4))->method('handleThrowable')
            ->with(self::identicalTo($original))->willThrowException($failure)
        ;
        $aggregate = new BestEffortThrowableHandlerAggregate($reporter, $reporter);

        for ($invocation = 0; $invocation < 2; ++$invocation) {
            try {
                $aggregate->handleThrowable($original);
                self::fail('Both duplicate slots must fail for this invocation.');
            } catch (AggregateReportingException $caught) {
                self::assertSame($original, $caught->getOriginalThrowable());
                self::assertSame([$failure, $failure], $caught->getFailures());
            }
        }
    }
}
