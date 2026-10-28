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

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Config\ArrayConfig;
use FastForward\EventDispatcher\ServiceProvider\EventDispatcherServiceProvider;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$mode = $argv[1] ?? 'legacy';
if (!in_array($mode, ['legacy', 'structured'], true)) {
    throw new InvalidArgumentException('Choose legacy or structured event logging.');
}

$deliveries = [];
$reported = [];

$logger = new class extends AbstractLogger {
    /**
     * @var list<array{mixed, string, array<string, mixed>}> synthetic private records
     */
    public array $records = [];

    /**
     * Records diagnostics without emitting them into an HTTP response.
     *
     * Untyped parameters also support applications using psr/log 1.x.
     *
     * @param mixed                $level   the PSR-3 log level
     * @param mixed                $message the diagnostic message
     * @param array<string, mixed> $context the application log context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
};

$throwables = new ThrowableListenerProvider(
    // @param Throwable $error the unchanged diagnostic also retained for the context assertion
    new ThrowableCallableListener(static function (Throwable $error) use (&$deliveries, &$reported): void {
        $deliveries[] = 'throwable: ' . $error->getMessage();
        $reported[] = $error;
    }),
);
$events = new ErrorEventListenerProvider(
    // @param ErrorEventInterface $event the wrapper carrying the same original throwable
    new ErrorEventCallableListener(static function (ErrorEventInterface $event) use (&$deliveries): void {
        $deliveries[] = 'event: ' . $event->getThrowable()->getMessage();
    }),
);
if ('structured' === $mode) {
    $reporter = new LogThrowableHandler($logger, LogLevel::CRITICAL, ['channel' => 'errors']);
    $events->attach(new ErrorEventCallableListener(
        /** @param ErrorEventInterface $event the wrapper retaining the original diagnostic */
        static function (ErrorEventInterface $event) use ($reporter): void {
            $reporter->handleThrowable($event->getThrowable());
        },
    ));
} else {
    $events->attach(new LogErrorEventListener($logger, LogLevel::CRITICAL));
}

$configuration = new ArrayConfig([
    ListenerProviderInterface::class => [$throwables, $events],
]);

$services = container($configuration, EventDispatcherServiceProvider::class, ErrorHandlerServiceProvider::class);

$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('Synthetic warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}

if ('structured' === $mode) {
    $original = $reported[0] ?? null;
    $record = $logger->records[0] ?? null;
    if (
        null === $original || 1 !== count($reported) || null === $record || 1 !== count($logger->records)
        || LogLevel::CRITICAL !== $record[0] || 'Synthetic warning' !== $record[1]
        || 2 !== count($record[2]) || 'errors' !== ($record[2]['channel'] ?? null)
        || $original !== ($record[2]['exception'] ?? null)
    ) {
        throw new RuntimeException('Expected one structured log retaining the original throwable.');
    }
}

echo implode(PHP_EOL, $deliveries) . PHP_EOL;
printf("Private log records: %d\n", count($logger->records));

if ('structured' === $mode) {
    echo 'Structured exception context: original throwable' . PHP_EOL;
}
