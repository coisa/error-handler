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

use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchErrorEventThrowableHandlerFactory
 *
 * A factory responsible for creating instances of DispatchErrorEventThrowableHandler.
 * This factory SHALL retrieve the EventDispatcherInterface from the container
 * to ensure proper dependency injection.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class DispatchErrorEventThrowableHandlerFactory
{
    /**
     * Creates an instance of DispatchErrorEventThrowableHandler.
     *
     * This method SHALL retrieve an EventDispatcherInterface from the container
     * and pass it to the DispatchErrorEventThrowableHandler constructor.
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return DispatchErrorEventThrowableHandler The configured handler instance.
     */
    public function __invoke(ContainerInterface $container): DispatchErrorEventThrowableHandler
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $container->get(EventDispatcherInterface::class);

        return new DispatchErrorEventThrowableHandler($eventDispatcher);
    }
}
