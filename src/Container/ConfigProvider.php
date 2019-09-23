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

namespace CoiSA\ErrorHandler\Container;

use CoiSA\ErrorHandler\Container\Factory\AliasFactory;
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
 * @package CoiSA\ErrorHandler\Container
 */
final class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array
     */
    public function getDependencies(): array
    {
        return [
            'factories' => $this->getFactories(),
        ];
    }

    /**
     * @return array
     */
    public function getFactories(): array
    {
        return [
            ErrorHandler::class                       => Factory\ErrorHandlerFactory::class,
            ErrorHandlerMiddleware::class             => Factory\ErrorHandlerMiddlewareFactory::class,
            DispatchErrorEventThrowableHandler::class => Factory\DispatchErrorEventThrowableHandlerFactory::class,
            DispatchThrowableHandler::class           => Factory\DispatchThrowableHandlerFactory::class,
            ThrowableHandlerAggregate::class          => Factory\ThrowableHandlerAggregateFactory::class,
            ThrowableHandlerInterface::class          => new AliasFactory(ThrowableHandlerAggregate::class),
            ThrowableResponseFactory::class           => Factory\ThrowableResponseFactoryFactory::class,
            ThrowableResponseFactoryInterface::class  => new AliasFactory(ThrowableResponseFactory::class),
            ThrowableStreamFactory::class             => Factory\ThrowableStreamFactoryFactory::class,
            ThrowableStreamFactoryInterface::class    => new AliasFactory(ThrowableStreamFactory::class),
        ];
    }
}
