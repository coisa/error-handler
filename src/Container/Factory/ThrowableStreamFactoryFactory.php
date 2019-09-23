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

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class ThrowableStreamFactoryFactory
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ThrowableStreamFactoryFactory
{
    /**
     * @param ContainerInterface $container
     *
     * @return ThrowableStreamFactory
     */
    public function __invoke(ContainerInterface $container): ThrowableStreamFactory
    {
        $streamFactory    = $container->get(StreamFactoryInterface::class);
        $throwableHandler = $container->get(ThrowableHandlerInterface::class);

        return new ThrowableStreamFactory($streamFactory, $throwableHandler);
    }
}
