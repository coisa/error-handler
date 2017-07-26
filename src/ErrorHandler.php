<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler;

/**
 * Class ErrorHandler
 * @package CoiSA\ErrorHandler
 */
class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * @var callable Error handler
     */
    private $handler;

    /**
     * @var int Error level type
     */
    private $error_types;

    /**
     * @var bool
     */
    private $prevent = false;

    /**
     * ErrorHandler constructor.
     *
     * @param callable $handler
     * @param int $error_types
     */
    public function __construct(callable $handler, int $error_types = E_ALL | E_STRICT)
    {
        ob_start();

        $this->initialize($handler, $error_types);
        $this->register();
    }

    /**
     * Restore previous error handler
     */
    public function __destruct()
    {
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Initialize object
     *
     * @param callable $handler
     * @param int $error_types
     */
    protected function initialize(callable $handler, int $error_types): void
    {
        $this->handler = $handler;
        $this->error_types = $error_types;
    }

    /**
     * Register error handler
     */
    private function register(): void
    {
        set_error_handler([$this, self::ERROR_HANDLER], $this->error_types);
        set_exception_handler([$this, self::EXCEPTION_HANDLER]);
        register_shutdown_function([$this, self::SHUTDOWN_HANDLER]);
    }

    /**
     * Handles php errors
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile [optional]
     * @param int $errline [optional]
     * @return bool
     * @throws \ErrorException
     */
    public function handleError(int $errno, string $errstr, string $errfile = null, int $errline = null): bool
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting, so let it fall
            // through to the standard PHP error handler
            return false;
        }

        throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline);
    }

    /**
     * Handles exception throws
     *
     * @param \Throwable $exception
     * @throws \Throwable
     */
    public function handleException(\Throwable $exception): void
    {
        /** @var callable $handler */
        $handler = $this->handler;

        if (ob_get_level()) {
            ob_end_clean();
        }

        try {
            ob_start();

            $signal = $handler($exception);
            ob_end_flush();

            if ($signal) {
                // @TODO Set HTTP_CODE
                exit($signal);
            }
        } catch (\Throwable $exception) {
            while (ob_get_level()) {
                ob_end_clean();
            }

            if ($this->prevent === false) {
                throw $exception;
            }
        }
    }

    /**
     * Handles shutdown function
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error) {
            $this->prevent = true;

            $exception = new \ErrorException(
                $error['message'], $error['type'], $error['type'],
                $error['file'],  $error['line']
            );

            $this->handleException($exception);
        }
    }
}