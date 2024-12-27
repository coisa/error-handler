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

namespace CoiSA\ErrorHandler\Container\Factory;

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class ThrowableHandlerAggregateFactory
 *
 * Factory responsible for creating and configuring an instance of ThrowableHandlerAggregate.
 * This factory SHALL attach available handlers from the container to the aggregate handler.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ThrowableHandlerAggregateFactory
{
    /**
     * Creates an instance of ThrowableHandlerAggregate.
     *
     * This method SHALL attach available handlers from the container if they exist:
     * - CallableThrowableHandler
     * - DispatchThrowableHandler
     * - DispatchErrorEventThrowableHandler
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return ThrowableHandlerAggregate The configured aggregate handler.
     */
    public function __invoke(ContainerInterface $container): ThrowableHandlerAggregate
    {
        $handler = new ThrowableHandlerAggregate();

        if ($container->has(CallableThrowableHandler::class)) {
            /** @var CallableThrowableHandler $callableHandler */
            $callableHandler = $container->get(CallableThrowableHandler::class);
            $handler->attach($callableHandler);
        }

        if ($container->has(EventDispatcherInterface::class)) {
            if ($container->has(DispatchThrowableHandler::class)) {
                /** @var DispatchThrowableHandler $dispatchHandler */
                $dispatchHandler = $container->get(DispatchThrowableHandler::class);
                $handler->attach($dispatchHandler);
            }

            if ($container->has(DispatchErrorEventThrowableHandler::class)) {
                /** @var DispatchErrorEventThrowableHandler $dispatchErrorHandler */
                $dispatchErrorHandler = $container->get(DispatchErrorEventThrowableHandler::class);
                $handler->attach($dispatchErrorHandler);
            }
        }

        return $handler;
    }
}
