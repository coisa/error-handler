<?php

/**
 * This file is part of coisa/error-handler.
 *
 * (c) Felipe Sayão Lobato Abreu <github@felipeabreu.com.br>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Handler;

/**
 * Interface PhpErrorHandlerInterface
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface PhpErrorHandlerInterface
{
    /**
     * Handle php runtime errors.
     * This method MAY throw an exception delegating errors to throwable handler.
     *
     * @param int    $code
     * @param string $message
     * @param string $filename
     * @param int    $line
     */
    public function handlePhpError(
        int $code,
        string $message,
        string $filename,
        int $line
    ): void;
}
