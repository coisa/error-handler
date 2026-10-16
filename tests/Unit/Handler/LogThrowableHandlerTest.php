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

use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Verifies direct logger delivery, context identity and unchanged failure propagation.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Handler\LogThrowableHandler
 */
final class LogThrowableHandlerTest extends TestCase
{
    /**
     * An adapter must not emit a diagnostic or response body of its own.
     */
    protected function setUp(): void
    {
        $this->expectOutputString('');
    }

    /**
     * Preserves exact messages and objects without interpreting their codes.
     *
     * @param string $message the exact synthetic message, including an empty boundary
     * @param int    $code    the error code that must not determine the log level
     * @param bool   $isError whether to report an Error instead of an Exception
     *
     * @dataProvider provideLogsOnceAtErrorWithTheOriginalThrowableCases
     */
    public function testLogsOnceAtErrorWithTheOriginalThrowable(string $message, int $code, bool $isError): void
    {
        $throwable = $isError ? new \Error($message, $code) : new \RuntimeException($message, $code);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::ERROR,
            $throwable->getMessage(),
            ['exception' => $throwable],
        );

        (new LogThrowableHandler($logger))->handleThrowable($throwable);
    }

    /**
     * Supplies independently observable message and throwable-kind boundaries.
     *
     * @return array<string, array{string, int, bool}> synthetic inputs compatible with PHPUnit process isolation
     */
    public static function provideLogsOnceAtErrorWithTheOriginalThrowableCases(): iterable
    {
        return [
            'exception' => ['Synthetic failure', 404, false],
            'error' => ['Synthetic engine error', 503, true],
            'empty message' => ['', 0, false],
        ];
    }

    /**
     * Context values and caller data survive while the original exception wins its key.
     */
    public function testPreservesCallerContextWithoutCoercionOrMutation(): void
    {
        $throwable = new \RuntimeException('Synthetic failure');
        $conflict = new \LogicException('Caller context');
        $object = new \stdClass();
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        $context = ['component' => 'worker', 'exception' => $conflict, 'object' => $object, 7 => $stream];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::CRITICAL,
            $throwable->getMessage(),
            self::callback(
                /**
                 * Observe values and identity without imposing context-key order.
                 *
                 * @param array<array-key, mixed> $received the logger's structured context
                 *
                 * @return bool whether all values match their original identities
                 */
                static function (array $received) use ($throwable, $object, $stream): bool {
                    self::assertCount(4, $received);
                    self::assertSame($throwable, $received['exception']);
                    self::assertSame('worker', $received['component']);
                    self::assertSame($object, $received['object']);
                    self::assertSame($stream, $received[7]);

                    return true;
                },
            ),
        );

        try {
            (new LogThrowableHandler($logger, LogLevel::CRITICAL, $context))->handleThrowable($throwable);

            self::assertSame($conflict, $context['exception']);
            self::assertSame($object, $context['object']);
            self::assertSame($stream, $context[7]);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Leaves supported-level policy to the logger, including its exact rejection object.
     */
    public function testPropagatesTheLoggersLevelRejectionWithoutRetry(): void
    {
        $failure = new InvalidArgumentException('Unsupported application level');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with('application-level', '', self::isType('array'))
            ->willThrowException($failure)
        ;
        $handler = new LogThrowableHandler($logger, 'application-level');

        try {
            $handler->handleThrowable(new \RuntimeException(''));

            self::fail('The logger failure must propagate.');
        } catch (\Throwable $observed) {
            self::assertSame($failure, $observed);
        }
    }

    /**
     * Propagates the identical backend failure after exactly one delivery attempt.
     */
    public function testPropagatesTheBackendFailureWithoutRetryOrOutput(): void
    {
        $failure = new \RuntimeException('Synthetic logger transport failure');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->willThrowException($failure);
        $handler = new LogThrowableHandler($logger);

        try {
            $handler->handleThrowable(new \Error('Original application failure'));

            self::fail('The logger failure must propagate.');
        } catch (\Throwable $observed) {
            self::assertSame($failure, $observed);
        }
    }

    /**
     * Keeps constructing a reporter free of diagnostic delivery.
     */
    public function testConstructionDoesNotLog(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('log');

        new LogThrowableHandler($logger);
    }
}
