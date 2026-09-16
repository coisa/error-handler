<?php

declare(strict_types=1);

namespace CoiSA\ErrorHandler\ServiceProvider;

use CoiSA\ErrorHandler\Container\Factory\AliasFactory;
use CoiSA\ErrorHandler\Container\Factory\DispatchErrorEventThrowableHandlerFactory;
use CoiSA\ErrorHandler\Container\Factory\DispatchThrowableHandlerFactory;
use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\Container\Factory\ThrowableHandlerAggregateFactory;
use CoiSA\ErrorHandler\Container\Factory\ThrowableResponseFactoryFactory;
use CoiSA\ErrorHandler\Container\Factory\ThrowableStreamFactoryFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
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
use Interop\Container\ServiceProviderInterface;

/** Declares services without installing PHP's process-wide callbacks. */
final class ErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /** @return array<class-string, callable> */
    public function getFactories(): array
    {
        return [
            ErrorHandlerInterface::class => new AliasFactory(ErrorHandler::class),
            ErrorHandler::class => new ErrorHandlerFactory(),
            ErrorHandlerFactory::class => static fn (): ErrorHandlerFactory => new ErrorHandlerFactory(),
            ThrowableHandlerInterface::class => new AliasFactory(ThrowableHandlerAggregate::class),
            ThrowableHandlerAggregate::class => new ThrowableHandlerAggregateFactory(),
            DispatchThrowableHandler::class => new DispatchThrowableHandlerFactory(),
            DispatchErrorEventThrowableHandler::class => new DispatchErrorEventThrowableHandlerFactory(),
            ThrowableResponseFactoryInterface::class => new AliasFactory(ThrowableResponseFactory::class),
            ThrowableResponseFactory::class => new ThrowableResponseFactoryFactory(),
            ThrowableStreamFactoryInterface::class => new AliasFactory(ThrowableStreamFactory::class),
            ThrowableStreamFactory::class => new ThrowableStreamFactoryFactory(),
            ErrorHandlerMiddleware::class => new ErrorHandlerMiddlewareFactory(),
            ErrorHandlerMiddlewareFactory::class => static fn (): ErrorHandlerMiddlewareFactory =>
                new ErrorHandlerMiddlewareFactory(),
        ];
    }

    /** @return array<string, callable> */
    public function getExtensions(): array
    {
        return [];
    }
}
