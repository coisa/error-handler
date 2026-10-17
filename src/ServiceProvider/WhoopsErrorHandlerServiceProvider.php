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

use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\CallableFactory;
use FastForward\Container\Factory\InvokableFactory;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\PrettyPageHandler;

/**
 * Selects optional pure development HTML after the major middleware reports.
 *
 * Applications supply HTTP factories, a private reporter and trusted development
 * authorization, and compose lifecycle services separately. Discovery creates no
 * page and installs no native callbacks.
 */
final class WhoopsErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /**
     * Describes only this profile's page, pure renderer and response alias.
     *
     * FastForward caches the configured page. Its lazy callback resolves that service
     * only during rendering, after the renderer checks its optional dependency.
     *
     * @return array<string, callable(ContainerInterface): mixed> the lazy service definitions
     */
    public function getFactories(): array
    {
        return [
            PrettyPageHandler::class => new InvokableFactory(PrettyPageHandler::class),
            WhoopsThrowableResponseFactory::class => new CallableFactory(
                /**
                 * @param ContainerInterface $container the application service resolver
                 *
                 * @return WhoopsThrowableResponseFactory the pure renderer with a lazy page service
                 */
                static function (ContainerInterface $container): WhoopsThrowableResponseFactory {
                    /** @var ResponseFactoryInterface $responses the application's PSR-17 factory */
                    $responses = $container->get(ResponseFactoryInterface::class);

                    /** @var StreamFactoryInterface $streams the application's PSR-17 factory */
                    $streams = $container->get(StreamFactoryInterface::class);

                    /** @var \Closure(): PrettyPageHandler $pageFactory resolves the configured page only during rendering */
                    $pageFactory = static fn() => $container->get(PrettyPageHandler::class);

                    return new WhoopsThrowableResponseFactory(
                        $responses,
                        $streams,
                        $pageFactory,
                    );
                },
            ),
            ThrowableResponseFactoryInterface::class => AliasFactory::get(WhoopsThrowableResponseFactory::class),
        ];
    }

    /**
     * Adds no extensions to the application's services.
     *
     * @return array<string, callable(ContainerInterface, mixed): mixed> the empty extension map
     */
    public function getExtensions(): array
    {
        return [];
    }
}
