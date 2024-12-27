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

namespace CoiSA\ErrorHandler\Container;

use Psr\Container\ContainerInterface;
use CoiSA\ErrorHandler\Container\Exception\ContainerException;
use CoiSA\ErrorHandler\Container\Exception\NotFoundException;

/**
 * Class ErrorHandlerContainer
 *
 * A custom container implementation for managing error handler services and dependencies.
 * This container SHALL resolve services using factories, aliases, or delegate resolution
 * to a parent container when configured.
 *
 * @package CoiSA\ErrorHandler\Container
 */
final class ErrorHandlerContainer implements ContainerInterface
{
    /**
     * @var ContainerInterface|null A parent container for delegation.
     */
    private ?ContainerInterface $container;

    /**
     * @var string[] List of factories mapped to their service IDs.
     */
    private array $factories;

    /**
     * @var string[] List of aliases mapped to their service IDs.
     */
    private array $aliases;

    /**
     * @var object[] Cached instances of resolved services.
     */
    private array $instances = [];

    /**
     * Constructs an ErrorHandlerContainer.
     *
     * @param ContainerInterface|null $container An optional parent container for delegation.
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $configProvider = new ConfigProvider();

        $this->factories = $configProvider->getFactories();
        $this->aliases   = $configProvider->getAliases();
        $this->container = $container;
    }

    /**
     * Checks if the container can resolve the given service ID.
     *
     * This method SHALL return true if the service ID can be resolved
     * either by delegation, aliases, or factories.
     *
     * @param string $id The identifier of the entry to look for.
     *
     * @return bool True if the service ID can be resolved, false otherwise.
     */
    public function has(string $id): bool
    {
        return ($this->container && $this->container->has($id))
            || array_key_exists($id, $this->aliases)
            || array_key_exists($id, $this->factories);
    }

    /**
     * Retrieves a service instance by its identifier.
     *
     * This method SHALL resolve the service by:
     * - Delegating to the parent container (if available)
     * - Using an alias to resolve the actual service
     * - Instantiating via a factory
     *
     * @param string $id The identifier of the entry to retrieve.
     *
     * @throws ContainerException If an error occurs during instantiation.
     * @throws NotFoundException If no entry was found for the given identifier.
     *
     * @return mixed The resolved service instance.
     */
    public function get(string $id)
    {
        if ($this->container && $this->container->has($id)) {
            return $this->container->get($id);
        }

        if (!isset($this->instances[$id])) {
            try {
                $this->instances[$id] = ($this->getFactory($id))($this);
            } catch (NotFoundException $notFoundException) {
                throw $notFoundException;
            } catch (\Throwable $throwable) {
                throw ContainerException::createFromThrowable($throwable);
            }
        }

        return $this->instances[$id];
    }

    /**
     * Resolves a factory for the given service identifier.
     *
     * This method SHALL resolve the service factory based on:
     * - Aliases mapping to another service
     * - Direct mapping to a factory class
     *
     * @param string $id The identifier of the entry to resolve.
     *
     * @throws NotFoundException If no factory or alias is found for the given identifier.
     *
     * @return callable A callable factory responsible for creating the service instance.
     */
    private function getFactory(string $id): callable
    {
        if (!$this->has($id)) {
            throw new NotFoundException(sprintf('Factory for class %s was not found', $id));
        }

        if (isset($this->aliases[$id])) {
            return new Factory\AliasFactory($this->aliases[$id]);
        }

        return new $this->factories[$id]();
    }
}
