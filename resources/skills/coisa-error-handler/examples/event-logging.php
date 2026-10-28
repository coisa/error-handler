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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use FastForward\Config\ArrayConfig;
use FastForward\EventDispatcher\ServiceProvider\EventDispatcherServiceProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

// The application owns this private sink and its real transport configuration.
$logger = new class extends AbstractLogger {
    /** @var list<array{mixed, string, array<string, mixed>}> synthetic observations */
    public array $records = [];

    /**
     * Retains a record without emitting diagnostics or contacting a real transport.
     *
     * @param mixed                $level   the unchanged PSR-3 level
     * @param mixed                $message the exact diagnostic message
     * @param array<string, mixed> $context the structured logging context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
};

$reporter = new LogThrowableHandler($logger, LogLevel::CRITICAL, ['channel' => 'errors']);
$listener = new ErrorEventCallableListener(
    /** @param ErrorEventInterface $event the wrapper retaining the original diagnostic */
    static function (ErrorEventInterface $event) use ($reporter): void {
        $reporter->handleThrowable($event->getThrowable());
    },
);

$configuration = new ArrayConfig([
    ListenerProviderInterface::class => [new ErrorEventListenerProvider($listener)],
]);

$services = container($configuration, EventDispatcherServiceProvider::class);
$dispatcher = $services->get(EventDispatcherInterface::class);
if (!$dispatcher instanceof EventDispatcherInterface) {
    throw new RuntimeException('Expected the application PSR-14 dispatcher.');
}

$errors = new ErrorHandler(new DispatchErrorEventThrowableHandler($dispatcher));
$original = new RuntimeException('Synthetic private event failure');

// Direct reporting does not install process callbacks or choose an exit status.
$errors->handleThrowable($original);

$record = $logger->records[0] ?? null;
if (
    1 !== count($logger->records) || null === $record
    || LogLevel::CRITICAL !== $record[0] || $original->getMessage() !== $record[1]
    || 2 !== count($record[2]) || 'errors' !== ($record[2]['channel'] ?? null)
    || $original !== ($record[2]['exception'] ?? null)
) {
    throw new RuntimeException('Expected one structured log with the identical original throwable.');
}

echo 'Event logging: one structured record with the original throwable passed' . PHP_EOL;
