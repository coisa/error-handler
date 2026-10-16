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
use PHPUnit\Framework\TestCase;

/**
 * Verifies original-object delivery and failure identity through the callable adapter.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Handler\CallableThrowableHandler
 */
final class CallableThrowableHandlerTest extends TestCase
{
    /** Each explicit invocation delivers the original object once and ignores callback results. */
    public function testRepeatedInputRemainsSeparateOriginalObjectDeliveries(): void
    {
        $original = new \RuntimeException('Synthetic callback event');
        $received = [];
        $handler = new CallableThrowableHandler(
            /**
             * Record identity while returning an application value the adapter ignores.
             *
             * @param \Throwable $error the reported original failure
             *
             * @return string an ignored callback result
             */
            static function (\Throwable $error) use (&$received): string {
                $received[] = $error;

                return 'ignored';
            },
        );
        self::assertSame([], $received);
        $handler->handleThrowable($original);
        $handler->handleThrowable($original);

        self::assertSame([$original, $original], $received);
    }

    /** Callback failure propagates without replacing the event or retrying delivery. */
    public function testPropagatesTheSameCallbackFailureOnce(): void
    {
        $original = new \RuntimeException('Synthetic reported event');
        $failure = new \Error('Synthetic reporter failure');
        $calls = 0;
        $handler = new CallableThrowableHandler(
            /**
             * Fail after observing the original event once.
             *
             * @param \Throwable $error the original reporting input
             *
             * @throws \Error the exact configured reporter failure
             */
            static function (\Throwable $error) use ($original, $failure, &$calls): void {
                self::assertSame($original, $error);
                ++$calls;

                throw $failure;
            },
        );
        $caught = null;

        try {
            $handler->handleThrowable($original);
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertSame($failure, $caught);
        self::assertSame(1, $calls);
    }
}
