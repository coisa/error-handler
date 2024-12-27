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

use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchThrowableHandlerFactory
 *
 * A factory responsible for creating instances of DispatchThrowableHandler.
 * This factory SHALL ensure proper dependency injection of the EventDispatcherInterface.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class DispatchThrowableHandlerFactory
{
    /**
     * Creates an instance of DispatchThrowableHandler.
     *
     * This method SHALL retrieve an EventDispatcherInterface from the container
     * and pass it to the DispatchThrowableHandler constructor.
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return DispatchThrowableHandler The configured handler instance.
     */
    public function __invoke(ContainerInterface $container): DispatchThrowableHandler
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $container->get(EventDispatcherInterface::class);

        return new DispatchThrowableHandler($eventDispatcher);
    }
}
