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
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$phpErrors = new class implements PhpErrorHandlerInterface {
    /**
     * @var list<string> reportable PHP messages received by this application handler
     */
    public array $messages = [];

    /**
     * Records reportable PHP errors without converting them to exceptions.
     *
     * @param int    $code     the PHP severity
     * @param string $message  the PHP message
     * @param string $filename the source filename
     * @param int    $line     the source line
     */
    public function handlePhpError(int $code, string $message, string $filename, int $line): void
    {
        if (($code & error_reporting()) !== 0) {
            $this->messages[] = $message;
        }
    }
};

$shutdowns = 0;
$shutdown = new CallableShutdownHandler(static function () use (&$shutdowns): void {
    ++$shutdowns;
});

$application = new ArrayServiceProvider([
    CallableThrowableHandler::class => static fn(): CallableThrowableHandler => new CallableThrowableHandler(
        /**
         * Routes diagnostics to the application's private sink.
         *
         * @param Throwable $error the original diagnostic
         */
        static function (Throwable $error): void {
            error_log($error->getMessage());
        },
    ),
    PhpErrorHandlerInterface::class => static fn(): PhpErrorHandlerInterface => $phpErrors,
    ShutdownHandlerInterface::class => static fn(): ShutdownHandlerInterface => $shutdown,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('Application-owned warning', E_USER_WARNING);
    $errors->handleShutdown(); // Simulate a notification without terminating the process.
} finally {
    $errors->unregister();
}

printf("PHP errors: %d | Shutdown notifications: %d\n", count($phpErrors->messages), $shutdowns);
