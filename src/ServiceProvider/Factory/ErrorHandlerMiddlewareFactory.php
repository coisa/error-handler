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

namespace CoiSA\ErrorHandler\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Psr\Container\ContainerInterface;

/** Creates a fresh HTTP lifecycle handler without reusing the cached process handler. */
final class ErrorHandlerMiddlewareFactory
{
    /**
     * Invokes the existing core factory for each new middleware scope.
     *
     * @param ContainerInterface $container application and library definitions
     *
     * @return ErrorHandlerMiddleware an unregistered wrapper with a distinct core handler
     *
     * @throws \Throwable a dependency or construction failure
     */
    public function __invoke(ContainerInterface $container): ErrorHandlerMiddleware
    {
        /** @var ErrorHandlerFactory $errorHandlerFactory */
        $errorHandlerFactory = $container->get(ErrorHandlerFactory::class);
        $errorHandler = $errorHandlerFactory($container);

        /** @var ThrowableResponseFactoryInterface $responseFactory */
        $responseFactory = $container->get(ThrowableResponseFactoryInterface::class);

        return new ErrorHandlerMiddleware($errorHandler, $responseFactory);
    }
}
