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
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\ErrorHandler\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Psr\Container\ContainerInterface;

/**
 * Builds middleware with a fresh handler separate from the process service.
 *
 * @package CoiSA\ErrorHandler\ServiceProvider\Factory
 */
final class ErrorHandlerMiddlewareFactory
{
    /**
     * Creates an independent middleware scope without registering PHP callbacks.
     *
     * @param ContainerInterface $container The container supplying a reusable handler factory and responses.
     *
     * @return ErrorHandlerMiddleware Middleware owning a fresh, initially unregistered handler.
     */
    public function __invoke(ContainerInterface $container): ErrorHandlerMiddleware
    {
        /**
         * @var ErrorHandlerFactory $handlerFactory
         */
        $handlerFactory = $container->get(ErrorHandlerFactory::class);
        /**
         * @var ThrowableResponseFactoryInterface $responses
         */
        $responses = $container->get(ThrowableResponseFactoryInterface::class);

        return new ErrorHandlerMiddleware($handlerFactory($container), $responses);
    }
}
