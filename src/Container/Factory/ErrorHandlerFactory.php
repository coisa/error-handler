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

namespace CoiSA\ErrorHandler\Container\Factory;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Container\ContainerInterface;

/**
 * Class ErrorHandlerFactory
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ErrorHandlerFactory
{
    /**
     * @param ContainerInterface $container
     *
     * @return ErrorHandler
     */
    public function __invoke(ContainerInterface $container): ErrorHandler
    {
        $throwableHandler = $container->get(ThrowableHandlerInterface::class);
        $phpErrorHandler  = $container->has(PhpErrorHandlerInterface::class) ? $container->get(PhpErrorHandlerInterface::class) : null;
        $shutdownHandler  = $container->has(ShutdownHandlerInterface::class) ? $container->get(ShutdownHandlerInterface::class) : null;

        return new ErrorHandler($throwableHandler, $phpErrorHandler, $shutdownHandler);
    }
}
