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
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\InvokableFactory;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Declares report-before-render JSON services for explicit composition with the base provider.
 */
final class JsonErrorHandlerServiceProvider implements ServiceProviderInterface
{
    /** @var bool whether trusted application configuration enables diagnostic JSON */
    private bool $debug;

    /**
     * Selects the JSON representation before container resolution begins.
     *
     * @param bool $debug explicit development diagnostics; public JSON is the default
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Composes a private reporter with pure JSON without importing lifecycle definitions.
     *
     * @return array<string, callable(ContainerInterface): mixed> JSON-only service definitions
     */
    public function getFactories(): array
    {
        return [
            JsonThrowableResponseFactory::class => new InvokableFactory(
                JsonThrowableResponseFactory::class,
                ResponseFactoryInterface::class,
                StreamFactoryInterface::class,
                $this->debug,
            ),
            ReportingThrowableResponseFactory::class => new InvokableFactory(
                ReportingThrowableResponseFactory::class,
                ThrowableHandlerInterface::class,
                JsonThrowableResponseFactory::class,
            ),
            ThrowableResponseFactoryInterface::class => AliasFactory::get(ReportingThrowableResponseFactory::class),
        ];
    }

    /**
     * Leaves application services unchanged.
     *
     * @return array<string, callable> no implicit extensions
     */
    public function getExtensions(): array
    {
        return [];
    }
}
