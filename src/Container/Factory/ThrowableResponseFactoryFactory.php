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

use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Class ThrowableResponseFactoryFactory
 *
 * Factory responsible for creating instances of ThrowableResponseFactory.
 * This factory SHALL ensure proper dependency injection of ThrowableStreamFactoryInterface
 * and ResponseFactoryInterface for configuring the ThrowableResponseFactory.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ThrowableResponseFactoryFactory
{
    /**
     * Creates an instance of ThrowableResponseFactory.
     *
     * This method SHALL retrieve the required dependencies from the container:
     * - ThrowableStreamFactoryInterface
     * - ResponseFactoryInterface
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return ThrowableResponseFactory The configured ThrowableResponseFactory instance.
     */
    public function __invoke(ContainerInterface $container): ThrowableResponseFactory
    {
        /** @var ThrowableStreamFactoryInterface $throwableStreamFactory */
        $throwableStreamFactory = $container->get(ThrowableStreamFactoryInterface::class);

        /** @var ResponseFactoryInterface $responseFactory */
        $responseFactory = $container->get(ResponseFactoryInterface::class);

        return new ThrowableResponseFactory($throwableStreamFactory, $responseFactory);
    }
}
