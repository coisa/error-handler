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

namespace CoiSA\ErrorHandler\Container;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;

/**
 * Class ConfigProvider
 *
 * Provides configuration for dependency injection containers.
 * This class SHALL define aliases, factories, and other dependencies
 * required for the proper functioning of the error-handler library.
 *
 * @package CoiSA\ErrorHandler\Container
 */
final class ConfigProvider
{
    /**
     * Invokes the configuration provider.
     *
     * This method SHALL return an array containing the configuration
     * for dependency injection containers, including dependencies, aliases,
     * and factories.
     *
     * @return array The configuration array.
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * Retrieves the dependencies configuration.
     *
     * This method SHALL define aliases and factories used for resolving
     * dependencies in the container.
     *
     * @return array An array containing aliases and factories.
     */
    public function getDependencies(): array
    {
        return [
            'aliases'   => $this->getAliases(),
            'factories' => $this->getFactories(),
        ];
    }

    /**
     * Retrieves service aliases.
     *
     * This method SHALL define class aliases to allow easier resolution
     * of services in the dependency injection container.
     *
     * @return array An associative array of aliases.
     */
    public function getAliases(): array
    {
        return [
            ThrowableHandlerInterface::class         => ThrowableHandlerAggregate::class,
            ThrowableResponseFactoryInterface::class => ThrowableResponseFactory::class,
            ThrowableStreamFactoryInterface::class   => ThrowableStreamFactory::class,
        ];
    }

    /**
     * Retrieves service factories.
     *
     * This method SHALL define factories responsible for instantiating
     * and configuring services in the dependency injection container.
     *
     * @return array An associative array of service factories.
     */
    public function getFactories(): array
    {
        return [
            ErrorHandler::class                       => Factory\ErrorHandlerFactory::class,
            ErrorHandlerMiddleware::class             => Factory\ErrorHandlerMiddlewareFactory::class,
            DispatchErrorEventThrowableHandler::class => Factory\DispatchErrorEventThrowableHandlerFactory::class,
            DispatchThrowableHandler::class           => Factory\DispatchThrowableHandlerFactory::class,
            ThrowableHandlerAggregate::class          => Factory\ThrowableHandlerAggregateFactory::class,
            ThrowableResponseFactory::class           => Factory\ThrowableResponseFactoryFactory::class,
            ThrowableStreamFactory::class             => Factory\ThrowableStreamFactoryFactory::class,
        ];
    }
}
