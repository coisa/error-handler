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
 * Adapts an application callable to the four-argument PHP-error interface.
 *
 * The callback owns mask/conversion policy. Its return value is ignored and
 * its failures propagate unchanged; native false/fall-through is not represented.
 */
final class CallablePhpErrorHandler implements PhpErrorHandlerInterface
{
    /** @var callable(int, string, string, int): mixed the application PHP-error callback */
    private $handler;

    /**
     * Store a callback without invoking it or installing native PHP handlers.
     *
     * @param callable(int, string, string, int): mixed $handler the explicit error policy
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Invoke the callback once with the original error metadata, ignoring its result.
     *
     * @param int    $code     the supplied PHP error level
     * @param string $message  the supplied diagnostic message
     * @param string $filename the supplied source filename
     * @param int    $line     the supplied source line
     *
     * @throws \Throwable the exact callback failure, without wrapping or retry
     */
    public function handlePhpError(int $code, string $message, string $filename, int $line): void
    {
        ($this->handler)($code, $message, $filename, $line);
    }
}
