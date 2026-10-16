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

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class PhpLastErrorShutdownHandler.
 *
 * Handles PHP fatal errors during the shutdown phase.
 * This class SHALL capture the last error using `error_get_last()` and delegate
 * its processing to the provided PhpErrorHandlerInterface instance if it is
 * classified as a catchable fatal error.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class PhpLastErrorShutdownHandler implements ShutdownHandlerInterface
{
    /**
     * @var PhpErrorHandlerInterface handles PHP errors detected during shutdown
     */
    private PhpErrorHandlerInterface $phpErrorHandler;

    /**
     * Constructs the PhpLastErrorShutdownHandler instance.
     *
     * @param PhpErrorHandlerInterface $phpErrorHandler the error handler for PHP errors
     */
    public function __construct(PhpErrorHandlerInterface $phpErrorHandler)
    {
        $this->phpErrorHandler = $phpErrorHandler;
    }

    /**
     * Handles the last PHP error during shutdown.
     *
     * This method SHALL retrieve the last error using `error_get_last()` and,
     * if it is classified as a catchable fatal error, delegate it to the error handler.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if (!\is_array($error) || !$this->isCatchableFatalError($error['type'])) {
            return;
        }

        $this->phpErrorHandler->handlePhpError(
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line'],
        );
    }

    /**
     * Determines if an error level represents a catchable fatal error.
     *
     * This method SHALL check if the provided error level corresponds
     * to one of the fatal error types: E_ERROR, E_PARSE, E_CORE_ERROR,
     * E_COMPILE_ERROR. Warnings are not fatal shutdown events.
     *
     * @param int $level the PHP error level to check
     *
     * @return bool true if the error is a catchable fatal error, false otherwise
     */
    private function isCatchableFatalError(int $level): bool
    {
        $errors = E_ERROR
            | E_PARSE
            | E_CORE_ERROR
            | E_COMPILE_ERROR;

        return ($level & $errors) > 0;
    }
}
