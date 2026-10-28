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

namespace CoiSA\ErrorHandler\Test\Unit\EventDispatcher\Listener;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verifies exact event delivery, ignored callback results and unchanged failures.
 *
 * @internal
 */
#[CoversClass(ErrorEventCallableListener::class)]
final class ErrorEventCallableListenerTest extends TestCase
{
    /**
     * Invoke once with the original event and return it even when the callback returns another.
     */
    public function testReturnsTheOriginalEventAfterOneCallback(): void
    {
        $original = self::createStub(ErrorEventInterface::class);
        $replacement = self::createStub(ErrorEventInterface::class);
        $observed = null;
        $calls = 0;
        $listener = new ErrorEventCallableListener(
            /**
             * Observe the event and return a replacement the listener must ignore.
             *
             * @param ErrorEventInterface $event the callback's original event
             *
             * @return ErrorEventInterface an unrelated replacement
             */
            static function (ErrorEventInterface $event) use (&$observed, &$calls, $replacement): ErrorEventInterface {
                $observed = $event;
                ++$calls;

                return $replacement;
            },
        );
        self::assertSame(0, $calls);

        self::assertSame($original, $listener($original));
        self::assertSame($original, $observed);
        self::assertSame(1, $calls);
    }

    /**
     * Propagate the callback's original failure without another delivery attempt.
     */
    public function testPropagatesTheIdenticalCallbackFailure(): void
    {
        $event = self::createStub(ErrorEventInterface::class);
        $failure = new \Error('Synthetic event callback failure');
        $calls = 0;
        $listener = new ErrorEventCallableListener(
            /**
             * Fail once after receiving the original event.
             *
             * @param ErrorEventInterface $received the event supplied by the listener
             *
             * @throws \Error the synthetic callback failure
             */
            static function (ErrorEventInterface $received) use ($event, $failure, &$calls): void {
                self::assertSame($event, $received);
                ++$calls;

                throw $failure;
            },
        );
        $observed = null;

        try {
            $listener($event);
        } catch (\Throwable $error) {
            $observed = $error;
        }

        self::assertSame($failure, $observed);
        self::assertSame(1, $calls);
    }
}
