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

use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class ThrowableStreamFactoryFactory
 *
 * Factory responsible for creating instances of ThrowableStreamFactory.
 * This factory SHALL ensure proper dependency injection of StreamFactoryInterface
 * and ThrowableHandlerInterface for configuring the ThrowableStreamFactory.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class ThrowableStreamFactoryFactory
{
    /**
     * Creates an instance of ThrowableStreamFactory.
     *
     * This method SHALL retrieve the required dependencies from the container:
     * - StreamFactoryInterface
     * - ThrowableHandlerInterface
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return ThrowableStreamFactory The configured ThrowableStreamFactory instance.
     */
    public function __invoke(ContainerInterface $container): ThrowableStreamFactory
    {
        /** @var StreamFactoryInterface $streamFactory */
        $streamFactory = $container->get(StreamFactoryInterface::class);

        /** @var ThrowableHandlerInterface $throwableHandler */
        $throwableHandler = $container->get(ThrowableHandlerInterface::class);

        return new ThrowableStreamFactory($streamFactory, $throwableHandler);
    }
}
