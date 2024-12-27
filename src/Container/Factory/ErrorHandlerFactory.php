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

namespace CoiSA\ErrorHandler\Container\Factory;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Container\ContainerInterface;

/**
 * Class ErrorHandlerFactory
 *
 * A factory responsible for creating instances of ErrorHandler.
 * This factory SHALL ensure proper dependency injection of ThrowableHandlerInterface,
 * PhpErrorHandlerInterface, and ShutdownHandlerInterface.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ErrorHandlerFactory
{
    /**
     * Creates an instance of ErrorHandler.
     *
     * This method SHALL retrieve the required handlers from the container
     * and inject them into the ErrorHandler constructor.
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return ErrorHandler The configured ErrorHandler instance.
     */
    public function __invoke(ContainerInterface $container): ErrorHandler
    {
        /** @var ThrowableHandlerInterface $throwableHandler */
        $throwableHandler = $container->get(ThrowableHandlerInterface::class);

        /** @var PhpErrorHandlerInterface|null $phpErrorHandler */
        $phpErrorHandler = $container->has(PhpErrorHandlerInterface::class)
            ? $container->get(PhpErrorHandlerInterface::class)
            : null;

        /** @var ShutdownHandlerInterface|null $shutdownHandler */
        $shutdownHandler = $container->has(ShutdownHandlerInterface::class)
            ? $container->get(ShutdownHandlerInterface::class)
            : null;

        return new ErrorHandler($throwableHandler, $phpErrorHandler, $shutdownHandler);
    }
}
