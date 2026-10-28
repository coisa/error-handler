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

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Verifies the explicit event-to-structured-log composition using existing adapters.
 *
 * @internal
 */
#[CoversClass(ErrorEventCallableListener::class)]
#[UsesClass(ErrorEvent::class)]
#[UsesClass(LogThrowableHandler::class)]
final class ErrorEventLoggingCompositionTest extends TestCase
{
    /** Original objects and caller context survive exactly one private log delivery. */
    public function testLogsTheOriginalThrowableWithStructuredContextOnce(): void
    {
        $original = new \RuntimeException('Synthetic event failure');
        $event = new ErrorEvent($original);
        $marker = new \stdClass();
        $replaced = new \LogicException('Caller-supplied exception entry');
        $context = ['channel' => 'audit', 'marker' => $marker, 'exception' => $replaced];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::CRITICAL,
            $original->getMessage(),
            self::callback(
                /** @param array<string, mixed> $values logger context; key order is not a contract */
                static fn(array $values): bool => 3 === \count($values)
                    && 'audit' === ($values['channel'] ?? null)
                    && $marker === ($values['marker'] ?? null)
                    && $original === ($values['exception'] ?? null),
            ),
        );
        $listener = $this->listener($logger, $context);

        $this->expectOutputString('');
        self::assertSame($event, $listener($event));
        self::assertSame($original, $event->getThrowable());
        self::assertSame($replaced, $context['exception']);
    }

    /** Logger transport failure propagates unchanged after one attempt, without a retry. */
    public function testPropagatesTheIdenticalLoggerFailureWithoutRetry(): void
    {
        $original = new \RuntimeException('Synthetic event failure');
        $event = new ErrorEvent($original);
        $failure = new \LogicException('Synthetic private logger failure');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::CRITICAL,
            $original->getMessage(),
            self::identicalTo(['exception' => $original]),
        )->willThrowException($failure);
        $listener = $this->listener($logger);
        $observed = null;

        $this->expectOutputString('');

        try {
            $listener($event);
        } catch (\Throwable $caught) {
            $observed = $caught;
        }

        self::assertSame($failure, $observed);
        self::assertSame($original, $event->getThrowable());
    }

    /**
     * Builds the same explicit bridge shown in the executable recipe.
     *
     * @param LoggerInterface      $logger  the application-controlled diagnostic sink
     * @param array<string, mixed> $context application metadata forwarded to that sink
     *
     * @return ErrorEventCallableListener the listener extracting the original diagnostic once
     */
    private function listener(LoggerInterface $logger, array $context = []): ErrorEventCallableListener
    {
        $reporter = new LogThrowableHandler($logger, LogLevel::CRITICAL, $context);

        return new ErrorEventCallableListener(
            /** @param ErrorEventInterface $event the current diagnostic wrapper */
            static function (ErrorEventInterface $event) use ($reporter): void {
                $reporter->handleThrowable($event->getThrowable());
            },
        );
    }
}
