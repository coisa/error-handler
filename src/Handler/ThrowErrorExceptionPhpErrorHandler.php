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

use CoiSA\ErrorHandler\Exception\ErrorException;

/**
 * Class ThrowErrorExceptionPhpErrorHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class ThrowErrorExceptionPhpErrorHandler implements PhpErrorHandlerInterface
{
    /**
     * @param int    $code
     * @param string $message
     * @param string $filename
     * @param int    $line
     *
     * @throws ErrorException
     */
    public function handlePhpError(int $code, string $message, string $filename, int $line): void
    {
        if (!(\error_reporting() & $code)) {
            return;
        }

        throw new ErrorException($message, $code, $code, $filename, $line);
    }
}
