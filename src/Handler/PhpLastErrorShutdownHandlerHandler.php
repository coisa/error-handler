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
 * Class PhpLastErrorShutdownHandlerHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class PhpLastErrorShutdownHandlerHandler implements ShutdownHandlerInterface
{
    /**
     * @var PhpErrorHandlerInterface
     */
    private $phpErrorHandler;

    /**
     * PhpLastErrorShutdownHandlerHandler constructor.
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

        if (false === \is_array($error)
            || $error['type'] !== E_ERROR
        ) {
            return;
        }

        $this->phpErrorHandler->handlePhpError(
            $error['type'],
            $error['message'],
            $error['file'],
            $error['line']
        );
    }
}
