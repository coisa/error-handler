<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentor.dev.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\ErrorHandler;

use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;

/**
 * Class ErrorHandler
 *
 * Handles errors, exceptions, and shutdown events within the application.
 * This class MUST adhere to PSR-1, PSR-12, and PER standards.
 *
 * @package CoiSA\ErrorHandler
 */
final class ErrorHandler implements
    ErrorHandlerInterface,
    ThrowableHandlerInterface,
    PhpErrorHandlerInterface,
    ShutdownHandlerInterface
{
    /**
     * @var PhpErrorHandlerInterface Handles PHP errors.
     */
    private PhpErrorHandlerInterface $phpErrorHandler;

    /**
     * @var ThrowableHandlerInterface Handles throwable exceptions.
     */
    private ThrowableHandlerInterface $throwableHandler;

    /**
     * @var ShutdownHandlerInterface Handles shutdown processes.
     */
    private ShutdownHandlerInterface $shutdownHandler;

    /**
     * @var bool Indicates if the error handler is registered.
     */
    private bool $isRegistered = false;

    /**
     * @var bool Whether this instance already has a retained PHP shutdown callback.
     */
    private bool $shutdownRegistered = false;

    /**
     * @var bool Prevents recursive delivery through the same reporter.
     */
    private bool $isHandlingThrowable = false;

    /**
     * @var bool Tracks PHP's temporary exception-callback removal during termination.
     */
    private bool $isHandlingUncaughtThrowable = false;

    /**
     * @var bool Prevents retrying a reporter that failed during the current registration.
     */
    private bool $handlerFailed = false;

    /**
     * @var \Closure(int, string, string, int): bool Stable callback identity for native PHP errors.
     */
    private \Closure $errorCallback;

    /**
     * @var \Closure(\Throwable): never Stable callback identity for uncaught throwables.
     */
    private \Closure $exceptionCallback;

    /**
     * Constructs the ErrorHandler.
     *
     * @param ThrowableHandlerInterface $throwableHandler Handles throwable exceptions.
     * @param ?PhpErrorHandlerInterface $phpErrorHandler Handles PHP errors.
     *     Defaults to ThrowErrorExceptionPhpErrorHandler.
     * @param ?ShutdownHandlerInterface $shutdownHandler Handles shutdown events.
     *     Defaults to PhpLastErrorShutdownHandler.
     */
    public function __construct(
        ThrowableHandlerInterface $throwableHandler,
        ?PhpErrorHandlerInterface $phpErrorHandler = null,
        ?ShutdownHandlerInterface $shutdownHandler = null
    ) {
        $this->throwableHandler = $throwableHandler;
        $this->phpErrorHandler = $phpErrorHandler ?? new ThrowErrorExceptionPhpErrorHandler();
        $this->shutdownHandler = $shutdownHandler ?? new PhpLastErrorShutdownHandler($this);
        $this->errorCallback = $this->onPhpError(...);
        $this->exceptionCallback = $this->onUncaughtThrowable(...);
    }

    /**
     * Registers the error handler.
     *
     * This method SHALL set handlers for errors, exceptions, and shutdown events.
     * It MUST prevent multiple registrations.
     */
    public function register(): void
    {
        if ($this->isRegistered) {
            return;
        }

        set_error_handler($this->errorCallback);
        set_exception_handler($this->exceptionCallback);
        if (!$this->shutdownRegistered) {
            register_shutdown_function([$this, 'handleShutdown']);
            $this->shutdownRegistered = true;
        }

        $this->handlerFailed = false;
        $this->isRegistered = true;
    }

    /**
     * Unregisters the error handler.
     *
     * This method SHALL restore the previous error and exception handlers.
     *
     * @throws \LogicException If another integration owns either active callback.
     */
    public function unregister(): void
    {
        if (!$this->isRegistered) {
            return;
        }

        if (!$this->hasActiveCallbacks()) {
            throw new \LogicException('Error handlers must be unregistered in reverse registration order.');
        }

        restore_error_handler();
        restore_exception_handler();

        $this->isRegistered = false;
    }

    /**
     * Handles throwable exceptions.
     *
     * @param \Throwable $throwable The throwable exception to handle.
     *
     * @throws \LogicException If the reporter recursively invokes this handler.
     * @throws \Throwable If reporting fails or the current registration already failed.
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        if ($this->isHandlingThrowable) {
            throw new \LogicException('Recursive throwable handling is not supported.', 0, $throwable);
        }
        if ($this->hasReporterFailed()) {
            throw $throwable;
        }

        $this->isHandlingThrowable = true;
        try {
            $this->throwableHandler->handleThrowable($throwable);
        } catch (\Throwable $failure) {
            $this->handlerFailed = $this->isRegistered;
            throw $failure;
        } finally {
            $this->isHandlingThrowable = false;
        }
    }

    /**
     * Indicates whether reporting has failed within the active registration.
     *
     * Integrations MUST propagate the current failure instead of rendering through
     * the same reporter again. Independent unregistered deliveries have no retained failure state.
     *
     * @return bool True when the active registration must not retry its reporter.
     */
    public function hasReporterFailed(): bool
    {
        return $this->isRegistered && $this->handlerFailed;
    }

    /**
     * Handles PHP errors.
     *
     * Converts PHP errors into ErrorException if required.
     *
     * @param int $code The error level.
     * @param string $message The error message.
     * @param string $filename The filename where the error occurred.
     * @param int $line The line number where the error occurred.
     *
     * @throws \Throwable If the PHP handler or reporter fails to process the error.
     */
    public function handlePhpError(int $code, string $message, string $filename, int $line): void
    {
        try {
            $this->phpErrorHandler->handlePhpError($code, $message, $filename, $line);
        } catch (\ErrorException $throwable) {
            $this->handleThrowable($throwable);
        }
    }

    /**
     * Handles shutdown events.
     *
     * Reports observable fatal errors or custom shutdown notifications while active.
     */
    public function handleShutdown(): void
    {
        if (!$this->isRegistered || $this->handlerFailed || !$this->hasActiveCallbacks(true)) {
            return;
        }

        try {
            $this->shutdownHandler->handleShutdown();
        } catch (\Throwable $throwable) {
            $this->handleThrowable($throwable);
        }
    }

    /**
     * Adapts PHP's error callback to the public error-handling contract.
     *
     * @param int $code The PHP error level.
     * @param string $message The error message.
     * @param string $filename The source filename.
     * @param int $line The source line number.
     *
     * @return bool True to suppress PHP's default handler after successful processing.
     *
     * @throws \Throwable If the PHP handler or reporter fails.
     */
    private function onPhpError(int $code, string $message, string $filename, int $line): bool
    {
        $this->handlePhpError($code, $message, $filename, $line);

        return true;
    }

    /**
     * Checks ownership without changing the native callback stacks or error masks.
     *
     * @param bool $duringShutdown Whether to allow PHP's cleared callback on the terminating path.
     *
     * @return bool True when this instance still owns both active callbacks.
     */
    private function hasActiveCallbacks(bool $duringShutdown = false): bool
    {
        // Probe without changing the existing stacks or their error-level masks.
        $currentErrorHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        $currentExceptionHandler = set_exception_handler(static function (\Throwable $error): void {
        });
        restore_exception_handler();

        // PHP clears the exception callback while invoking it. exit() keeps that
        // temporary state through shutdown, but does not transfer ownership.
        $ownsExceptionCallback = $currentExceptionHandler === $this->exceptionCallback
            || ($duringShutdown && $this->isHandlingUncaughtThrowable && $currentExceptionHandler === null);

        return $currentErrorHandler === $this->errorCallback && $ownsExceptionCallback;
    }

    /**
     * Reports an uncaught throwable and terminates with a failure exit status.
     *
     * @param \Throwable $throwable The original uncaught throwable.
     *
     * @return never Successful reporting terminates the process with exit status 1.
     *
     * @throws \Throwable If reporting fails and PHP must take its native failure path.
     */
    private function onUncaughtThrowable(\Throwable $throwable): never
    {
        $this->isHandlingUncaughtThrowable = true;
        $this->handleThrowable($throwable);
        exit(1);
    }
}
