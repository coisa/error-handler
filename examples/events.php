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

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

require __DIR__ . '/bootstrap.php';

$failure = new RuntimeException('Synthetic event failure');
$rawCalls = 0;
$wrappedCalls = 0;
$logger = new class extends AbstractLogger {
    /** @var array<int, array{level: mixed, message: string}> Synthetic in-memory log records. */
    public array $records = [];

    /**
     * Record a synthetic log entry using signatures compatible with PSR-3 1.x–3.x.
     *
     * @param mixed                $level   the selected log level
     * @param object|string        $message the string or stringable event message
     * @param array<string, mixed> $context application log context
     */
    public function log($level, $message, array $context = []): void
    {
        if (is_object($message) && method_exists($message, '__toString')) {
            $message = $message->__toString();
        }

        exampleCheck(is_string($message), 'A PSR-3 message must be a string or stringable object.');

        $this->records[] = ['level' => $level, 'message' => $message];
    }
};
$rawListeners = new ThrowableListenerProvider(new ThrowableCallableListener(
    /**
     * Verify that raw dispatch retains the original throwable.
     *
     * @param Throwable $error the raw PSR-14 event
     */
    static function (Throwable $error) use ($failure, &$rawCalls): void {
        exampleCheck($error === $failure, 'Raw event identity changed.');

        ++$rawCalls;
    },
));
$wrappedListeners = new ErrorEventListenerProvider(new ErrorEventCallableListener(
    /**
     * Verify that the wrapper exposes its original throwable and string representation.
     *
     * @param ErrorEventInterface $event the wrapped PSR-14 event
     */
    static function (ErrorEventInterface $event) use ($failure, &$wrappedCalls): void {
        exampleCheck($event->getThrowable() === $failure, 'Wrapped event identity changed.');
        exampleCheck((string) $event === (string) $failure, 'Wrapped event text changed.');

        ++$wrappedCalls;
    },
));
$wrappedListeners->attach(new LogErrorEventListener($logger, LogLevel::CRITICAL));

$providers = new ListenerProviderAggregate();
$providers->attach($rawListeners);
$providers->attach($wrappedListeners);

$dispatcher = new EventDispatcher($providers);

(new DispatchThrowableHandler($dispatcher))->handleThrowable($failure);
(new DispatchErrorEventThrowableHandler($dispatcher))->handleThrowable($failure);

exampleCheck(1 === $rawCalls && 1 === $wrappedCalls && 1 === count($logger->records), 'Event routing changed.');
exampleCheck(LogLevel::CRITICAL === $logger->records[0]['level'], 'The configured log level was ignored.');

echo 'raw throwable, ErrorEvent, listener providers and PSR-3 logging passed' . PHP_EOL;
