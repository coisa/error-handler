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

use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verifies raw-event identity and dispatcher failure propagation.
 *
 * @internal
 */
#[CoversClass(DispatchThrowableHandler::class)]
final class DispatchThrowableHandlerTest extends TestCase
{
    /** Dispatch the original throwable once even when the dispatcher returns another object. */
    public function testDispatchesTheOriginalThrowableOnce(): void
    {
        $original = new \RuntimeException('Synthetic raw event');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::identicalTo($original))
            ->willReturn(new \stdClass())
        ;

        (new DispatchThrowableHandler($dispatcher))->handleThrowable($original);
    }

    /** A dispatcher failure escapes unchanged without a second dispatch. */
    public function testPropagatesTheSameDispatcherFailure(): void
    {
        $original = new \RuntimeException('Synthetic raw event');
        $failure = new \RuntimeException('Synthetic dispatcher failure');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::identicalTo($original))
            ->willThrowException($failure)
        ;
        $caught = null;

        try {
            (new DispatchThrowableHandler($dispatcher))->handleThrowable($original);
        } catch (\Throwable $error) {
            $caught = $error;
        }

        self::assertSame($failure, $caught);
    }
}
