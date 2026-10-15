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
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\Factory\InvokableFactory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class ConfigProvider
 *
 * Provides configuration for dependency injection containers.
 * This class SHALL define aliases, factories, and other dependencies
 * required for the proper functioning of the error-handler library.
 *
 * @package CoiSA\ErrorHandler\Container
 * @phpstan-type Factories array<class-string, class-string|callable>
 * @phpstan-type Dependencies array{aliases: array<class-string, class-string>, factories: Factories}
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
     * @return array{dependencies: Dependencies} The configuration array.
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
     * @return Dependencies An array containing aliases and factories.
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
     * @return array<class-string, class-string> An associative array of aliases.
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
     * @return Factories An associative array of service factories.
     */
    public function getFactories(): array
    {
        return [
            ErrorHandler::class                       => Factory\ErrorHandlerFactory::class,
            ErrorHandlerMiddleware::class             => ErrorHandlerMiddlewareFactory::class,
            Factory\ErrorHandlerFactory::class        => new InvokableFactory(Factory\ErrorHandlerFactory::class),
            DispatchErrorEventThrowableHandler::class => new InvokableFactory(
                DispatchErrorEventThrowableHandler::class,
                EventDispatcherInterface::class
            ),
            DispatchThrowableHandler::class           => new InvokableFactory(
                DispatchThrowableHandler::class,
                EventDispatcherInterface::class
            ),
            ThrowableHandlerAggregate::class          => Factory\ThrowableHandlerAggregateFactory::class,
            ThrowableResponseFactory::class           => new InvokableFactory(
                ThrowableResponseFactory::class,
                ThrowableStreamFactoryInterface::class,
                ResponseFactoryInterface::class
            ),
            ThrowableStreamFactory::class             => new InvokableFactory(
                ThrowableStreamFactory::class,
                StreamFactoryInterface::class,
                ThrowableHandlerInterface::class
            ),
        ];
    }
}
