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

namespace CoiSA\ErrorHandler;

use CoiSA\ErrorHandler\Handler\CallablePhpErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;

/**
 * Class ErrorHandler.
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
     * @var PhpErrorHandlerInterface handles PHP errors
     */
    private PhpErrorHandlerInterface $phpErrorHandler;

    /**
     * @var ThrowableHandlerInterface handles throwable exceptions
     */
    private ThrowableHandlerInterface $throwableHandler;

    /**
     * @var ShutdownHandlerInterface handles shutdown processes
     */
    private ShutdownHandlerInterface $shutdownHandler;

    /**
     * @var bool indicates if the error handler is registered
     */
    private bool $isRegistered = false;

    /**
     * @var bool whether this instance already has a retained PHP shutdown callback
     */
    private bool $shutdownRegistered = false;

    /**
     * @var bool prevents recursive delivery through the same reporter
     */
    private bool $isHandlingThrowable = false;

    /**
     * @var bool tracks PHP's temporary exception-callback removal during termination
     */
    private bool $isHandlingUncaughtThrowable = false;

    /**
     * @var bool prevents retrying a reporter that failed during the current registration
     */
    private bool $handlerFailed = false;

    /**
     * @var \Closure(int, string, string, int): bool stable callback identity for native PHP errors
     */
    private \Closure $errorCallback;

    /**
     * @var \Closure(\Throwable): never stable callback identity for uncaught throwables
     */
    private \Closure $exceptionCallback;

    /**
     * Injects interface implementations or normalizes callables without invoking them.
     *
     * Supplied interfaces take precedence over an object's callable capability and
     * retain their identity. Callback results are ignored by the matching adapters.
     * Native PHP registration remains explicit; default shutdown routes through this
     * handler, preserving its reporting and PHP-error policy.
     *
     * @param callable|ThrowableHandlerInterface     $throwableHandler the required reporter
     * @param null|callable|PhpErrorHandlerInterface $phpErrorHandler  null keeps the default PHP-error policy
     * @param null|callable|ShutdownHandlerInterface $shutdownHandler  null keeps default shutdown reporting
     *
     * @phpstan-param ThrowableHandlerInterface|(callable(\Throwable): mixed) $throwableHandler
     * @phpstan-param PhpErrorHandlerInterface|(callable(int, string, string, int): mixed)|null $phpErrorHandler
     * @phpstan-param ShutdownHandlerInterface|(callable(): mixed)|null $shutdownHandler
     */
    public function __construct(
        ThrowableHandlerInterface|callable $throwableHandler,
        PhpErrorHandlerInterface|callable|null $phpErrorHandler = null,
        ShutdownHandlerInterface|callable|null $shutdownHandler = null,
    ) {
        $this->throwableHandler = $throwableHandler instanceof ThrowableHandlerInterface
            ? $throwableHandler
            : new CallableThrowableHandler($throwableHandler);

        $phpErrorHandler ??= new ThrowErrorExceptionPhpErrorHandler();
        $this->phpErrorHandler = $phpErrorHandler instanceof PhpErrorHandlerInterface
            ? $phpErrorHandler
            : new CallablePhpErrorHandler($phpErrorHandler);

        $shutdownHandler ??= new PhpLastErrorShutdownHandler($this);
        $this->shutdownHandler = $shutdownHandler instanceof ShutdownHandlerInterface
            ? $shutdownHandler
            : new CallableShutdownHandler($shutdownHandler);

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
     * @throws \LogicException if another integration owns either active callback
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
     * @param \Throwable $throwable the throwable exception to handle
     *
     * @throws \LogicException if the reporter recursively invokes this handler
     * @throws \Throwable      if reporting fails or the current registration already failed
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        if ($this->isHandlingThrowable) {
            throw new \LogicException('Recursive throwable handling is not supported.', 0, $throwable);
        }
        if ($this->isRegistered && $this->handlerFailed) {
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
     * Handles PHP errors.
     *
     * Converts PHP errors into ErrorException if required.
     *
     * @param int    $code     the error level
     * @param string $message  the error message
     * @param string $filename the filename where the error occurred
     * @param int    $line     the line number where the error occurred
     *
     * @throws \Throwable if the PHP handler or reporter fails to process the error
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
     * @param int    $code     the PHP error level
     * @param string $message  the error message
     * @param string $filename the source filename
     * @param int    $line     the source line number
     *
     * @return bool true to suppress PHP's default handler after successful processing
     *
     * @throws \Throwable if the PHP handler or reporter fails
     */
    private function onPhpError(int $code, string $message, string $filename, int $line): bool
    {
        $this->handlePhpError($code, $message, $filename, $line);

        return true;
    }

    /**
     * Checks ownership without changing the native callback stacks or error masks.
     *
     * @param bool $duringShutdown whether to allow PHP's cleared callback on the terminating path
     *
     * @return bool true when this instance still owns both active callbacks
     */
    private function hasActiveCallbacks(bool $duringShutdown = false): bool
    {
        // Probe without changing the existing stacks or their error-level masks.
        $currentErrorHandler = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $currentExceptionHandler = set_exception_handler(static function (\Throwable $error): void {
        });
        restore_exception_handler();

        // PHP clears the exception callback while invoking it. exit() keeps that
        // temporary state through shutdown, but does not transfer ownership.
        $ownsExceptionCallback = $currentExceptionHandler === $this->exceptionCallback
            || ($duringShutdown && $this->isHandlingUncaughtThrowable && null === $currentExceptionHandler);

        return $currentErrorHandler === $this->errorCallback && $ownsExceptionCallback;
    }

    /**
     * Reports an uncaught throwable and terminates with a failure exit status.
     *
     * @param \Throwable $throwable the original uncaught throwable
     *
     * @return never successful reporting terminates the process with exit status 1
     *
     * @throws \Throwable if reporting fails and PHP must take its native failure path
     */
    private function onUncaughtThrowable(\Throwable $throwable): never
    {
        $this->isHandlingUncaughtThrowable = true;
        $this->handleThrowable($throwable);

        exit(1);
    }
}
