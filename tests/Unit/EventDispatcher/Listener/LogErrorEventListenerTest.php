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
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verifies the legacy event listener logs string output and returns the same event.
 *
 * @internal
 */
#[CoversClass(LogErrorEventListener::class)]
final class LogErrorEventListenerTest extends TestCase
{
    /**
     * Forward the full event string once at the default or configured level.
     *
     * @param bool   $customLevel whether to configure an application-selected level
     * @param string $message     the event's exact string representation
     */
    #[DataProvider('provideLogsTheEventStringAndReturnsTheOriginalCases')]
    public function testLogsTheEventStringAndReturnsTheOriginal(bool $customLevel, string $message): void
    {
        $event = $this->createMock(ErrorEventInterface::class);
        $event->expects(self::once())->method('__toString')->willReturn($message);
        $event->expects(self::never())->method('getThrowable');
        $logger = $this->createMock(LoggerInterface::class);
        $level = $customLevel ? LogLevel::CRITICAL : LogLevel::ERROR;
        $logger->expects(self::once())->method('log')->with($level, $message, []);
        $listener = $customLevel ? new LogErrorEventListener($logger, $level) : new LogErrorEventListener($logger);

        $this->expectOutputString('');
        self::assertSame($event, $listener($event));
    }

    /**
     * Supply level and empty-string boundaries without constructing shared mocks.
     *
     * @return array<string, array{bool, string}> independent event-string inputs
     */
    public static function provideLogsTheEventStringAndReturnsTheOriginalCases(): iterable
    {
        return [
            'default error' => [false, 'Full synthetic event string'],
            'configured critical' => [true, 'Configured event string'],
            'empty event string' => [false, ''],
        ];
    }

    /**
     * Preserve the logger's exact failure without retry or output from this listener.
     */
    public function testPropagatesTheIdenticalLoggerFailure(): void
    {
        $failure = new \RuntimeException('Synthetic logger failure');
        $event = $this->createMock(ErrorEventInterface::class);
        $event->expects(self::once())->method('__toString')->willReturn('Synthetic event');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with('application-level', 'Synthetic event', [])
            ->willThrowException($failure)
        ;
        $listener = new LogErrorEventListener($logger, 'application-level');
        $observed = null;

        $this->expectOutputString('');

        try {
            $listener($event);
        } catch (\Throwable $error) {
            $observed = $error;
        }

        self::assertSame($failure, $observed);
    }

    /**
     * A failed event string conversion propagates before the logger receives anything.
     */
    public function testPropagatesTheEventStringFailureBeforeLogging(): void
    {
        $failure = new \Error('Synthetic event string failure');
        $event = $this->createMock(ErrorEventInterface::class);
        $event->expects(self::once())->method('__toString')->willThrowException($failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('log');
        $listener = new LogErrorEventListener($logger);
        $observed = null;

        $this->expectOutputString('');

        try {
            $listener($event);
        } catch (\Throwable $error) {
            $observed = $error;
        }

        self::assertSame($failure, $observed);
    }
}
