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
 * Class PhpLastErrorShutdownHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class PhpLastErrorShutdownHandler implements ShutdownHandlerInterface
{
    /**
     * @var PhpErrorHandlerInterface
     */
    private $phpErrorHandler;

    /**
     * PhpLastErrorShutdownHandler constructor.
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

        if (!\is_array($error) || !$this->isCatchableFatalError($error['type'])) {
            return;
        }

        $this->phpErrorHandler->handlePhpError(
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );
    }

    /**
     * @param int $level
     *
     * @return bool
     */
    private function isCatchableFatalError(int $level)
    {
        $errors = E_ERROR;
        $errors |= E_PARSE;
        $errors |= E_CORE_ERROR;
        $errors |= E_CORE_WARNING;
        $errors |= E_COMPILE_ERROR;
        $errors |= E_COMPILE_WARNING;

        return ($level & $errors) > 0;
    }
}
