<?php

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class PhpLastErrorShutdownHandlerHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class PhpLastErrorShutdownHandlerHandler implements ShutdownHandlerInterface
{
    /**
     * @var PhpErrorHandlerInterface
     */
    private $phpErrorHandler;

    /**
     * PhpLastErrorShutdownHandlerHandler constructor.
     *
     * @param PhpErrorHandlerInterface $phpErrorHandler
     */
    public function __construct(
        PhpErrorHandlerInterface $phpErrorHandler
    ) {
        $this->phpErrorHandler = $phpErrorHandler;
    }

    /**
     * Handle last php error
     */
    public function handleShutdown(): void
    {
        $error = \error_get_last();

        if (false === is_array($error)
            || $error['type'] !== E_ERROR
        ) {
            return;
        }

        $this->phpErrorHandler->handlePhpError(
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );
    }
}
