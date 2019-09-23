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
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ThrowableHandlerAggregateFactory
{
    /**
     * @param ContainerInterface $container
     *
     * @return ThrowableHandlerAggregate
     */
    public function __invoke(ContainerInterface $container): ThrowableHandlerAggregate
    {
        $handler = new ThrowableHandlerAggregate();

        if ($container->has(CallableThrowableHandler::class)) {
            $handler->attach($container->get(CallableThrowableHandler::class));
        }

        if ($container->has(EventDispatcherInterface::class)) {
            $handler->attach($container->get(DispatchThrowableHandler::class));
            $handler->attach($container->get(DispatchErrorEventThrowableHandler::class));
        }

        return $handler;
    }
}
