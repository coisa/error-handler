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

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Verifies wrapper creation without losing the original throwable or dispatcher failure.
 *
 * @internal
 */
#[CoversClass(DispatchErrorEventThrowableHandler::class)]
#[UsesClass(ErrorEvent::class)]
final class DispatchErrorEventThrowableHandlerTest extends TestCase
{
    /** Dispatch one concrete ErrorEvent wrapping the exact original throwable. */
    public function testDispatchesOneWrapperContainingTheOriginalThrowable(): void
    {
        $original = new \RuntimeException('Synthetic wrapped event');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
            /**
             * Verify the public event boundary rather than the wrapper's implementation.
             *
             * @param object $event the dispatched event
             *
             * @return bool whether the original throwable is preserved
             */
            static fn(object $event): bool => $event instanceof ErrorEvent && $original === $event->getThrowable(),
        ))->willReturn(new \stdClass());

        (new DispatchErrorEventThrowableHandler($dispatcher))->handleThrowable($original);
    }

    /** A failure in wrapped dispatch propagates once as the same object. */
    public function testPropagatesTheSameDispatcherFailure(): void
    {
        $original = new \RuntimeException('Synthetic input');
        $failure = new \Error('Synthetic wrapped dispatcher failure');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(ErrorEvent::class))
            ->willThrowException($failure)
        ;
        $caught = null;

        try {
            (new DispatchErrorEventThrowableHandler($dispatcher))->handleThrowable($original);
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertSame($failure, $caught);
    }
}
