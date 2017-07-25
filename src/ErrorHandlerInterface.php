<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler;

/**
 * Interface ErrorHandlerInterface
 * @package CoiSA\ErrorHandler
 */
interface ErrorHandlerInterface
{
    /** @const string Error handler method name */
    const ERROR_HANDLER = 'handleError';

    /** @const string Helper handler method name */
    const EXCEPTION_HANDLER = 'handleException';

    /** @const string Shutdown handler method name */
    const SHUTDOWN_HANDLER = 'handleShutdown';

    /**
     * @param callable $handler
     * @param int $error_types
     */
    public function __construct(callable $handler, int $error_types);

    /**
     * @param int $errno
     * @param string $errstr
     * @param string $errfile optional
     * @param int $errline optional
     * @return bool
     * @throws \ErrorException
     */
    public function handleError(int $errno, string $errstr, string $errfile = null, int $errline = null): bool;

    /**
     * @param \Throwable $exception
     */
    public function handleException(\Throwable $exception);

    /**
     * @return void
     */
    public function handleShutdown(): void;
}