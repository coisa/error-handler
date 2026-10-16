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

use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use PHPUnit\Framework\TestCase;

/**
 * Verifies explicit zero-argument delivery and unchanged callback failures.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Handler\CallableShutdownHandler
 */
final class CallableShutdownHandlerTest extends TestCase
{
    /** Construction is inert; each explicit shutdown call invokes the callback once. */
    public function testInvokesTheCallbackWithoutArgumentsOnlyWhenRequested(): void
    {
        $calls = 0;
        $handler = new CallableShutdownHandler(
            /** @return string an ignored application result */
            static function () use (&$calls): string {
                self::assertSame(0, \func_num_args());
                ++$calls;

                return 'ignored';
            },
        );

        self::assertSame(0, $calls);
        $handler->handleShutdown();
        $handler->handleShutdown();
        self::assertSame(2, $calls);
    }

    /** Callback failure escapes unchanged and is not retried by the adapter. */
    public function testPropagatesTheSameCallbackFailureOnce(): void
    {
        $failure = new \Error('Synthetic shutdown callback failure');
        $calls = 0;
        $handler = new CallableShutdownHandler(
            /** @throws \Error the exact application callback failure */
            static function () use ($failure, &$calls): void {
                ++$calls;

                throw $failure;
            },
        );
        $caught = null;

        try {
            $handler->handleShutdown();
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertSame($failure, $caught);
        self::assertSame(1, $calls);
    }
}
