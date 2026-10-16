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

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\InvokableFactory;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\PrettyPageHandler;

/**
 * Selects optional development HTML and explicit reporting for the 2.x middleware.
 *
 * Applications supply HTTP factories, a private reporter and trusted development
 * authorization. Discovery creates no page and installs no native callbacks.
 */
final class WhoopsErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /** @var \Closure(): PrettyPageHandler creates a fresh configured diagnostic page */
    private \Closure $pageHandlerFactory;

    /**
     * Configures the page callback without executing it or requiring Whoops yet.
     *
     * @param null|(callable(): PrettyPageHandler) $pageHandlerFactory creates a fresh page; null selects the default
     */
    public function __construct(?callable $pageHandlerFactory = null)
    {
        $this->pageHandlerFactory = \Closure::fromCallable(
            $pageHandlerFactory ?? [self::class, 'createPage'],
        );
    }

    /**
     * Composes legacy lifecycle defaults with this profile's pure renderer and reporter.
     *
     * The Closure is a constructor value, not an ID for container resolution.
     * FastForward owns service caching; the page callback creates per-render state.
     *
     * @return array<string, callable(ContainerInterface): mixed> the lazy service definitions
     */
    public function getFactories(): array
    {
        return array_merge(
            (new ErrorHandlerServiceProvider())->getFactories(),
            [
                WhoopsThrowableResponseFactory::class => new InvokableFactory(
                    WhoopsThrowableResponseFactory::class,
                    ResponseFactoryInterface::class,
                    StreamFactoryInterface::class,
                    $this->pageHandlerFactory,
                ),
                ReportingThrowableResponseFactory::class => new InvokableFactory(
                    ReportingThrowableResponseFactory::class,
                    ThrowableHandlerInterface::class,
                    WhoopsThrowableResponseFactory::class,
                ),
                ThrowableResponseFactoryInterface::class => AliasFactory::get(ReportingThrowableResponseFactory::class),
            ],
        );
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

    /** @return PrettyPageHandler a new unregistered default diagnostic page */
    private static function createPage(): PrettyPageHandler
    {
        return new PrettyPageHandler();
    }
}
