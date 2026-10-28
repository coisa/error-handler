<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Resolves an optional Console destination without registering a process or resolving a request. */
final class SymfonyCliThrowableHandlerFactory
{
    /**
     * Constructs the explicitly selected CLI reporter with application output or native stderr.
     *
     * @param ContainerInterface $container the application resolver supplying an optional output
     *
     * @return SymfonyCliThrowableHandler the inert diagnostic reporter
     *
     * @throws \Throwable the unchanged optional-output discovery or construction failure
     * @throws \TypeError when an explicitly configured output does not implement OutputInterface
     */
    public function __invoke(ContainerInterface $container): SymfonyCliThrowableHandler
    {
        $output = null;
        if ($container->has(OutputInterface::class)) {
            $output = $container->get(OutputInterface::class);
            if (!$output instanceof OutputInterface) {
                throw new \TypeError(OutputInterface::class . ' must resolve to an OutputInterface instance.');
            }
        }

        return new SymfonyCliThrowableHandler($output);
    }
}
