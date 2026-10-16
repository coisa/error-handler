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

use CoiSA\ErrorHandler\Exception\ErrorException;

/**
 * Class ThrowErrorExceptionPhpErrorHandler.
 *
 * Handles PHP errors by converting them into ErrorException instances.
 * This handler SHALL throw an ErrorException if the error level is part of the current error reporting level.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class ThrowErrorExceptionPhpErrorHandler implements PhpErrorHandlerInterface
{
    /**
     * Handles a PHP runtime error.
     *
     * This method SHALL throw an ErrorException if the error level is part of the current
     * error reporting configuration. Otherwise, it SHALL return without taking any action.
     *
     * @param int    $code     The error level code (e.g., E_WARNING, E_NOTICE).
     * @param string $message  the error message
     * @param string $filename the filename where the error occurred
     * @param int    $line     the line number where the error occurred
     *
     * @throws ErrorException if the error level is part of the current error reporting configuration
     */
    public function handlePhpError(int $code, string $message, string $filename, int $line): void
    {
        if (!(error_reporting() & $code)) {
            return;
        }

        throw ErrorException::fromPhpError($message, $code, $code, $filename, $line);
    }
}
