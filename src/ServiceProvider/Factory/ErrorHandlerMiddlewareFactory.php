<?php

declare(strict_types=1);

namespace CoiSA\ErrorHandler\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Psr\Container\ContainerInterface;

/** Builds middleware with its own handler, separate from the process singleton. */
final class ErrorHandlerMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): ErrorHandlerMiddleware
    {
        /** @var ErrorHandlerFactory $handlerFactory */
        $handlerFactory = $container->get(ErrorHandlerFactory::class);
        /** @var ThrowableResponseFactoryInterface $responses */
        $responses = $container->get(ThrowableResponseFactoryInterface::class);

        return new ErrorHandlerMiddleware($handlerFactory($container), $responses);
    }
}
