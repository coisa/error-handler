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

namespace CoiSA\ErrorHandler\Exception;

/**
 * Class ErrorException
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
     * @param string $message The error message.
     * @param int $code The error code.
     * @param int $severity The severity level of the error.
     * @param string|null $filename The filename where the error occurred.
     * @param int|null $line The line number where the error occurred.
     * @param \Throwable|null $previous The previous throwable used for exception chaining.
     *
     * @return self An instance of ErrorException.
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
