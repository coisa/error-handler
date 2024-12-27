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
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\ErrorHandler\Handler;

/**
 * Interface PhpErrorHandlerInterface
 *
 * Defines the contract for handling PHP runtime errors.
 * Implementing classes MUST provide a mechanism to process PHP errors,
 * optionally converting them into throwable exceptions.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface PhpErrorHandlerInterface
{
    /**
     * Handles PHP runtime errors.
     *
     * This method SHALL process PHP errors based on their error code, message,
     * filename, and line number. It MAY throw an exception to delegate
     * the error handling to a throwable handler.
     *
     * @param int $code The error level code (e.g., E_WARNING, E_NOTICE).
     * @param string $message The error message.
     * @param string $filename The filename where the error occurred.
     * @param int $line The line number where the error occurred.
     *
     * @throws \ErrorException MAY throw an exception if the error needs
     *                         to be escalated to a throwable handler.
     *
     * @return void
     */
    public function handlePhpError(
        int $code,
        string $message,
        string $filename,
        int $line
    ): void;
}
