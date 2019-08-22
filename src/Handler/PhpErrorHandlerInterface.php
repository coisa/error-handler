<?php

namespace CoiSA\ErrorHandler\Handler;

/**
 * Interface PhpErrorHandlerInterface
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface PhpErrorHandlerInterface
{
    /**
     * @param int $code
     * @param string $message
     * @param string $filename
     * @param int $line
     */
    public function handlePhpError(
        int $code,
        string $message,
        string $filename,
        int $line
    ): void;
}
