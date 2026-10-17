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

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Verifies ordered reporting and throwable identity across aggregate composition.
 *
 * @internal
 */
#[CoversClass(ThrowableHandlerAggregate::class)]
#[UsesClass(CallableThrowableHandler::class)]
final class ThrowableHandlerAggregateTest extends TestCase
{
    /**
     * Preserves constructor order, appended handlers and the original throwable.
     */
    public function testReportsOriginalThrowableInOrderIncludingAttachedHandler(): void
    {
        $throwable = new \RuntimeException('Synthetic aggregate event');
        $calls = [];

        /**
         * Creates a real reporter that records its position and received event.
         *
         * @param string $name the reporter's stable label in the observed sequence
         *
         * @return CallableThrowableHandler the reporter used to observe public behavior
         */
        $reporter = static function (string $name) use (&$calls): CallableThrowableHandler {
            return new CallableThrowableHandler(
                /**
                 * Records an event without replacing or mutating it.
                 *
                 * @param \Throwable $received the throwable passed by the aggregate
                 */
                static function (\Throwable $received) use ($name, &$calls): void {
                    $calls[] = [$name, $received];
                },
            );
        };

        $aggregate = new ThrowableHandlerAggregate($reporter('first'), $reporter('second'));
        $aggregate->attach($reporter('attached'));

        $aggregate->handleThrowable($throwable);

        self::assertSame([
            ['first', $throwable],
            ['second', $throwable],
            ['attached', $throwable],
        ], $calls);
    }

    /** A failed slot stops its pass without replacing the failure or poisoning later invocations. */
    public function testFailureStopsDeliveryAndAnIndependentPassCanSucceed(): void
    {
        $original = new \RuntimeException('First diagnostic');
        $independent = new \Error('Independent diagnostic');
        $failure = new \Error('Second reporter failed');
        $calls = [];
        $attempts = 0;
        $aggregate = new ThrowableHandlerAggregate(
            new CallableThrowableHandler(
                /** @param \Throwable $received the unchanged diagnostic supplied to the first slot */
                static function (\Throwable $received) use (&$calls): void {
                    $calls[] = ['first', $received];
                },
            ),
            new CallableThrowableHandler(
                /**
                 * Fail the first pass after recording the original diagnostic.
                 *
                 * @param \Throwable $received the unchanged diagnostic supplied to the failing slot
                 *
                 * @throws \Error the identical first-pass reporter failure
                 */
                static function (\Throwable $received) use (&$calls, &$attempts, $failure): void {
                    $calls[] = ['second', $received];
                    if (1 === ++$attempts) {
                        throw $failure;
                    }
                },
            ),
            new CallableThrowableHandler(
                /** @param \Throwable $received the unchanged diagnostic delivered only after earlier slots succeed */
                static function (\Throwable $received) use (&$calls): void {
                    $calls[] = ['third', $received];
                },
            ),
        );
        $this->expectOutputString('');

        try {
            $aggregate->handleThrowable($original);
            self::fail('The failing reporter must stop the current pass.');
        } catch (\Error $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame([['first', $original], ['second', $original]], $calls);

        $aggregate->handleThrowable($independent);

        self::assertSame([
            ['first', $original],
            ['second', $original],
            ['first', $independent],
            ['second', $independent],
            ['third', $independent],
        ], $calls);
    }
}
