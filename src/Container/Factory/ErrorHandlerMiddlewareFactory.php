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
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Class ErrorHandlerMiddlewareFactory
 *
 * Factory responsible for creating instances of ErrorHandlerMiddleware.
 * This factory SHALL ensure proper dependency injection of ErrorHandler and
 * ThrowableResponseFactoryInterface to configure the middleware appropriately.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ErrorHandlerMiddlewareFactory
{
    /**
     * Creates an instance of ErrorHandlerMiddleware.
     *
     * This method SHALL retrieve the required dependencies from the container
     * and inject them into the ErrorHandlerMiddleware constructor.
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return ErrorHandlerMiddleware The configured middleware instance.
     */
    public function __invoke(ContainerInterface $container): ErrorHandlerMiddleware
    {
        /** @var ErrorHandler $errorHandler */
        $errorHandler = $container->get(ErrorHandler::class);

        /** @var ThrowableResponseFactoryInterface $throwableResponseFactory */
        $throwableResponseFactory = $container->get(ThrowableResponseFactoryInterface::class);

        return new ErrorHandlerMiddleware($errorHandler, $throwableResponseFactory);
    }
}
