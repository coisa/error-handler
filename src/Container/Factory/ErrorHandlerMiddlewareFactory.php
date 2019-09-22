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
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Class ErrorHandlerMiddlewareFactory
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ErrorHandlerMiddlewareFactory
{
    /**
     * @param ContainerInterface $container
     *
     * @return ErrorHandlerMiddleware
     */
    public function __invoke(ContainerInterface $container): ErrorHandlerMiddleware
    {
        $errorHandler             = $container->get(ErrorHandler::class);
        $throwableResponseFactory = $container->get(ThrowableResponseFactoryInterface::class);

        return new ErrorHandlerMiddleware($errorHandler, $throwableResponseFactory);
    }
}
