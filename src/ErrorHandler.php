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

    private bool $shutdownRegistered = false;

    private bool $isHandlingThrowable = false;

    private bool $isHandlingUncaughtThrowable = false;

    private bool $handlerFailed = false;

    private \Closure $errorCallback;

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
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        if ($this->isHandlingThrowable) {
            throw new \LogicException('Recursive throwable handling is not supported.', 0, $throwable);
        }
        if ($this->handlerFailed) {
            throw $throwable;
        }

        $this->isHandlingThrowable = true;
        try {
            $this->throwableHandler->handleThrowable($throwable);
        } catch (\Throwable $failure) {
            $this->handlerFailed = true;
            throw $failure;
        } finally {
            $this->isHandlingThrowable = false;
        }
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
     * @throws \ErrorException When a PHP error is escalated.
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

    private function onPhpError(int $code, string $message, string $filename, int $line): bool
    {
        $this->handlePhpError($code, $message, $filename, $line);

        return true;
    }

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

    private function onUncaughtThrowable(\Throwable $throwable): never
    {
        $this->isHandlingUncaughtThrowable = true;
        $this->handleThrowable($throwable);
        exit(1);
    }
}
