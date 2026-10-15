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

namespace CoiSA\ErrorHandler\ServiceProvider;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ThrowableHandlerAggregateFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\InvokableFactory;
use Interop\Container\ServiceProviderInterface;

/**
 * Declares lifecycle services and interface aliases for FastForward autowiring.
 *
 * Resolving a service MUST NOT install PHP's process-wide callbacks.
 * Concrete dispatch and HTTP adapters SHALL be resolved through autowiring.
 *
 * @package CoiSA\ErrorHandler\ServiceProvider
 */
final class ErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /**
     * Supplies aliases and factories that define reporter composition and handler lifetimes.
     *
     * @return array<class-string, callable> Lazy service definitions.
     */
    public function getFactories(): array
    {
        return [
            ErrorHandlerInterface::class => AliasFactory::get(ErrorHandler::class),
            ErrorHandler::class => new ErrorHandlerFactory(),
            ErrorHandlerFactory::class => new InvokableFactory(ErrorHandlerFactory::class),
            ThrowableHandlerInterface::class => AliasFactory::get(ThrowableHandlerAggregate::class),
            ThrowableHandlerAggregate::class => new ThrowableHandlerAggregateFactory(),
            ThrowableResponseFactoryInterface::class => AliasFactory::get(ThrowableResponseFactory::class),
            ThrowableStreamFactoryInterface::class => AliasFactory::get(ThrowableStreamFactory::class),
            ErrorHandlerMiddleware::class => new ErrorHandlerMiddlewareFactory(),
            ErrorHandlerMiddlewareFactory::class => new InvokableFactory(ErrorHandlerMiddlewareFactory::class),
        ];
    }

    /**
     * Supplies no service extensions; applications own registration and decoration.
     *
     * @return array<string, callable> An empty extension map.
     */
    public function getExtensions(): array
    {
        return [];
    }
}
