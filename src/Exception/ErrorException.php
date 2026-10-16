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

namespace CoiSA\ErrorHandler\Exception;

/**
 * Class ErrorException.
 *
 * Represents a specialized error exception used within the error-handler package.
 * This exception SHALL provide enhanced error-handling capabilities while maintaining
 * compatibility with PHP's native \ErrorException.
 *
 * @package CoiSA\ErrorHandler\Exception
 */
final class ErrorException extends \ErrorException
{
    /**
     * Creates an instance of ErrorException from a PHP error.
     *
     * This method SHALL provide a factory for creating exceptions from native PHP errors.
     *
     * @param string          $message  the error message
     * @param int             $code     the error code
     * @param int             $severity the severity level of the error
     * @param null|string     $filename the filename where the error occurred
     * @param null|int        $line     the line number where the error occurred
     * @param null|\Throwable $previous the previous throwable used for exception chaining
     *
     * @return self an instance of ErrorException
     */
    public static function fromPhpError(
        string $message,
        int $code,
        int $severity,
        ?string $filename = null,
        ?int $line = null,
        ?\Throwable $previous = null
    ): self {
        return new self($message, $code, $severity, $filename ?? '', $line ?? 0, $previous);
    }
}
