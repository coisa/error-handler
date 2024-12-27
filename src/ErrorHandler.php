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

        set_error_handler([$this, 'handlePhpError']);
        set_exception_handler([$this, 'handleThrowable']);
        register_shutdown_function([$this, 'handleShutdown']);

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
        $this->throwableHandler->handleThrowable($throwable);
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
     * Ensures graceful shutdown and captures any remaining errors.
     */
    public function handleShutdown(): void
    {
        if (!$this->isRegistered) {
            return;
        }

        try {
            $this->shutdownHandler->handleShutdown();
        } catch (\Throwable $throwable) {
            $this->handleThrowable($throwable);
        }
    }
}
