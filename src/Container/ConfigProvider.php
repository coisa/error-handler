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

namespace CoiSA\ErrorHandler\Container;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler;

/**
 * Class ConfigProvider
 *
 * @package CoiSA\ErrorHandler\Container
 */
final class ConfigProvider
{
    /**
     * @return array
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    /**
     * @return array
     */
    public function getDependencies(): array
    {
        return [
            'factories' => $this->getFactories(),
        ];
    }

    /**
     * @return array
     */
    public function getFactories(): array
    {
        return [
            ErrorHandler::class                               => Factory\ErrorHandlerFactory::class,
            Handler\ThrowableHandlerInterface::class          => Factory\ThrowableHandlerAggregateFactory::class,
            Handler\DispatchErrorEventThrowableHandler::class => Factory\DispatchErrorEventThrowableHandlerFactory::class,
            Handler\DispatchThrowableHandler::class           => Factory\DispatchThrowableHandlerFactory::class,
        ];
    }
}
