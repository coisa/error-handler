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

use Psr\Container\ContainerInterface;

/**
 * Class AliasFactory
 *
 * A factory class responsible for resolving a service alias from a container.
 * This class SHALL retrieve the service instance associated with the provided alias.
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class AliasFactory
{
    /**
     * @var string The alias name of the service to retrieve.
     */
    private string $alias;

    /**
     * Constructs the AliasFactory with a service alias.
     *
     * @param string $alias The alias of the service to be retrieved from the container.
     */
    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

    /**
     * Invokes the factory to retrieve the service instance from the container.
     *
     * This method SHALL use the alias to fetch the corresponding service from the container.
     *
     * @param ContainerInterface $container The PSR-11 container instance.
     *
     * @return mixed The resolved service instance associated with the alias.
     */
    public function __invoke(ContainerInterface $container): mixed
    {
        return $container->get($this->alias);
    }
}
