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

use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchErrorEventThrowableHandlerFactory
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class DispatchErrorEventThrowableHandlerFactory
{
    /**
     * @param ContainerInterface $container
     *
     * @return DispatchErrorEventThrowableHandler
     */
    public function __invoke(ContainerInterface $container): DispatchErrorEventThrowableHandler
    {
        $eventDispatcher = $container->get(EventDispatcherInterface::class);

        return new DispatchErrorEventThrowableHandler($eventDispatcher);
    }
}
