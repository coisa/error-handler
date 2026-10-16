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
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$deliveries = [];

$logger = new class extends AbstractLogger {
    /**
     * @var list<string> synthetic private log records; replace with an application logger
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
        $this->records[] = $level . ': ' . (string) $message;
    }
};

$throwables = new ThrowableListenerProvider(
    new ThrowableCallableListener(static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'throwable: ' . $error->getMessage();
    }),
);
$events = new ErrorEventListenerProvider(
    new ErrorEventCallableListener(static function (ErrorEventInterface $event) use (&$deliveries): void {
        $deliveries[] = 'event: ' . $event->getThrowable()->getMessage();
    }),
);
$events->attach(new LogErrorEventListener($logger, LogLevel::CRITICAL));

$dispatcher = new EventDispatcher(new ListenerProviderAggregate($throwables, $events));

$application = new ArrayServiceProvider([
    EventDispatcherInterface::class => static fn(): EventDispatcherInterface => $dispatcher,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('Synthetic warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}

echo implode(PHP_EOL, $deliveries) . PHP_EOL;
printf("Private log records: %d\n", count($logger->records));
