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

namespace CoiSA\ErrorHandler\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\PrettyPageHandler;

/** Wires the explicitly selected optional Whoops renderer and its application-owned page. */
final class WhoopsThrowableResponseFactoryFactory
{
    /**
     * Resolves HTTP dependencies while deferring the configured page until rendering.
     *
     * @param ContainerInterface $container the application service resolver
     *
     * @return WhoopsThrowableResponseFactory the pure renderer with a lazy page service
     *
     * @throws \Throwable when an HTTP dependency fails or the optional Whoops package is missing
     */
    public function __invoke(ContainerInterface $container): WhoopsThrowableResponseFactory
    {
        /** @var ResponseFactoryInterface $responses the application's PSR-17 factory */
        $responses = $container->get(ResponseFactoryInterface::class);

        /** @var StreamFactoryInterface $streams the application's PSR-17 factory */
        $streams = $container->get(StreamFactoryInterface::class);

        /** @var \Closure(): PrettyPageHandler $pageFactory resolves the configured page only during rendering */
        $pageFactory = static fn() => $container->get(PrettyPageHandler::class);

        return new WhoopsThrowableResponseFactory($responses, $streams, $pageFactory);
    }
}
