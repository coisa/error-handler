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
/**
 * @package CoiSA\ErrorHandler\Container\Factory
 */
namespace CoiSA\ErrorHandler\Container\Factory;

use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchThrowableHandlerFactory.php
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class DispatchThrowableHandlerFactory
{
    /**
     * @param ContainerInterface $container
     *
     * @return DispatchThrowableHandler
     */
    public function __invoke(ContainerInterface $container): DispatchThrowableHandler
    {
        $eventDispatcher = $container->get(EventDispatcherInterface::class);

        return new DispatchThrowableHandler($eventDispatcher);
    }
}
