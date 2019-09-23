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

use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Class ThrowableResponseFactoryFactory
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ThrowableResponseFactoryFactory
{
    /**
     * @param ContainerInterface $container
     *
     * @return ThrowableResponseFactory
     */
    public function __invoke(ContainerInterface $container): ThrowableResponseFactory
    {
        $throwableStreamFactory = $container->get(ThrowableStreamFactoryInterface::class);
        $responseFactory        = $container->get(ResponseFactoryInterface::class);

        return new ThrowableResponseFactory($throwableStreamFactory, $responseFactory);
    }
}
