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

namespace CoiSA\ErrorHandler\ServiceProvider;

use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\InvokableFactory;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;

/**
 * Defines optional FastForward lifecycle services while retaining the 2.x capture defaults.
 *
 * Applications supply their private reporter and PSR-17 implementations. Discovery
 * never installs native callbacks; middleware scopes are created by an explicit factory.
 */
final class ErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /**
     * Describes cached process services and reusable factories for fresh HTTP scopes.
     *
     * @return array<string, callable(ContainerInterface): mixed> inert service definitions
     */
    public function getFactories(): array
    {
        return [
            ErrorHandlerInterface::class => AliasFactory::get(ErrorHandler::class),
            ErrorHandler::class => new ErrorHandlerFactory(),
            ErrorHandlerFactory::class => new InvokableFactory(ErrorHandlerFactory::class),
            ErrorHandlerMiddleware::class => new ErrorHandlerMiddlewareFactory(),
            ErrorHandlerMiddlewareFactory::class => new InvokableFactory(ErrorHandlerMiddlewareFactory::class),
            ThrowableResponseFactoryInterface::class => AliasFactory::get(ThrowableResponseFactory::class),
            ThrowableStreamFactoryInterface::class => AliasFactory::get(ThrowableStreamFactory::class),
        ];
    }

    /**
     * Leaves application services unchanged.
     *
     * @return array<string, callable> no implicit extensions
     */
    public function getExtensions(): array
    {
        return [];
    }
}
