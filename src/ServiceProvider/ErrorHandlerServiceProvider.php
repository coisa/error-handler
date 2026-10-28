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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\HtmlThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\NegotiatingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\GenericThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ThrowableHandlerAggregateFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\NegotiatingThrowableResponseFactoryFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\WhoopsThrowableResponseFactoryFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\SymfonyCliThrowableHandlerFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\InvokableFactory;
use Interop\Container\ServiceProviderInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\PrettyPageHandler;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * Declares lifecycle services and interface aliases for FastForward autowiring.
 *
 * Resolving a service MUST NOT install PHP's process-wide callbacks.
 * HTTP representation is selected for each request by a shared, stateless negotiator.
 * Whoops remains an explicitly selected optional adapter, independent of the default HTML renderer.
 *
 * @package CoiSA\ErrorHandler\ServiceProvider
 */
final class ErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /**
     * Selects trusted diagnostics without enabling them from request headers.
     *
     * @param bool $debug whether JSON and Symfony HTML may include development diagnostics
     * @param bool $cli   explicitly enables terminal diagnostics without inferring the server API
     */
    public function __construct(
        private readonly bool $debug = false,
        private readonly bool $cli = false,
    ) {
    }

    /**
     * Supplies aliases and factories that define reporter composition and handler lifetimes.
     *
     * @return array<class-string, callable> lazy service definitions
     */
    public function getFactories(): array
    {
        return [
            ErrorHandlerInterface::class => AliasFactory::get(ErrorHandler::class),
            ErrorHandler::class => new ErrorHandlerFactory(),
            ErrorHandlerFactory::class => new InvokableFactory(ErrorHandlerFactory::class),
            ThrowableHandlerInterface::class => AliasFactory::get(ThrowableHandlerAggregate::class),
            ThrowableHandlerAggregate::class => new ThrowableHandlerAggregateFactory($this->cli),
            SymfonyCliThrowableHandler::class => new SymfonyCliThrowableHandlerFactory(),
            ThrowableResponseFactoryInterface::class => AliasFactory::get(NegotiatingThrowableResponseFactory::class),
            NegotiatingThrowableResponseFactory::class => new NegotiatingThrowableResponseFactoryFactory(),
            JsonThrowableResponseFactory::class => new InvokableFactory(
                JsonThrowableResponseFactory::class,
                ResponseFactoryInterface::class,
                StreamFactoryInterface::class,
                SerializerInterface::class,
                $this->debug,
            ),
            SerializerInterface::class => AliasFactory::get(Serializer::class),
            Serializer::class => new InvokableFactory(
                Serializer::class,
                [new ProblemNormalizer(true)],
                [new JsonEncoder()],
            ),
            HtmlThrowableResponseFactory::class => new InvokableFactory(
                HtmlThrowableResponseFactory::class,
                ResponseFactoryInterface::class,
                StreamFactoryInterface::class,
                $this->debug,
            ),
            PrettyPageHandler::class => new InvokableFactory(PrettyPageHandler::class),
            WhoopsThrowableResponseFactory::class => new WhoopsThrowableResponseFactoryFactory(),
            ThrowableStreamFactoryInterface::class => AliasFactory::get(GenericThrowableStreamFactory::class),
            ErrorHandlerMiddleware::class => new ErrorHandlerMiddlewareFactory(),
            ErrorHandlerMiddlewareFactory::class => new InvokableFactory(ErrorHandlerMiddlewareFactory::class),
        ];
    }

    /**
     * Supplies no service extensions; applications own registration and decoration.
     *
     * @return array<string, callable> an empty extension map
     */
    public function getExtensions(): array
    {
        return [];
    }
}
