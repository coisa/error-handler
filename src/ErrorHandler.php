<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler;

use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandlerHandler;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;

/**
 * Class ErrorHandler
 *
 * @package CoiSA\ErrorHandler
 */
class ErrorHandler implements ErrorHandlerInterface,
    ThrowableHandlerInterface,
    PhpErrorHandlerInterface,
    ShutdownHandlerInterface
{
    /**
     * @var PhpErrorHandlerInterface
     */
    private $phpErrorHandler;

    /**
     * @var ThrowableHandlerInterface
     */
    private $trowableHandler;

    /**
     * @var ShutdownHandlerInterface
     */
    private $shutdownHandler;

    /**
     * @var bool
     */
    private $isRegistered = false;

    /**
     * ErrorHandler constructor.
     *
     * @param ThrowableHandlerInterface $throwableHandler
     * @param PhpErrorHandlerInterface|null $phpErrorHandler
     * @param ShutdownHandlerInterface|null $shutdownHandler
     */
    public function __construct(
        ThrowableHandlerInterface $throwableHandler,
        PhpErrorHandlerInterface $phpErrorHandler = null,
        ShutdownHandlerInterface $shutdownHandler = null
    ) {
        $this->trowableHandler = $throwableHandler;
        $this->phpErrorHandler = $phpErrorHandler ?? new ThrowErrorExceptionPhpErrorHandler();
        $this->shutdownHandler = $shutdownHandler ?? new PhpLastErrorShutdownHandlerHandler($this->phpErrorHandler);
    }

    /**
     * Register error handler
     */
    public function register(): void
    {
        if ($this->isRegistered) {
            return;
        }

        \set_error_handler([$this, 'handlePhpError']);
        \set_exception_handler([$this, 'handleThrowable']);
        \register_shutdown_function([$this, 'handleShutdown']);

        $this->isRegistered = true;
    }

    /**
     * Unregister error-handler
     */
    public function unregister(): void
    {
        if (false === $this->isRegistered) {
            return;
        }

        \restore_error_handler();
        \restore_exception_handler();

        $this->isRegistered = false;
    }

    /**
     * @param \Throwable $throwable
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $this->trowableHandler->handleThrowable($throwable);
    }

    /**
     * @param int $code
     * @param string $message
     * @param string $filename
     * @param int $line
     *
     * @throws \ErrorException
     */
    public function handlePhpError(int $code, string $message, string $filename, int $line): void
    {
        $this->phpErrorHandler->handlePhpError($code, $message, $filename, $line);
    }

    /**
     * Handle shutdown if registered
     */
    public function handleShutdown(): void
    {
        if (false === $this->isRegistered) {
            return;
        }

        $this->shutdownHandler->handleShutdown();
    }
}
