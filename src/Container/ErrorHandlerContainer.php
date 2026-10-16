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

namespace CoiSA\ErrorHandler\Container;

use Psr\Container\ContainerInterface;
use CoiSA\ErrorHandler\Container\Exception\ContainerException;
use CoiSA\ErrorHandler\Container\Exception\NotFoundException;

/**
 * Class ErrorHandlerContainer.
 *
 * A custom container implementation for managing error handler services and dependencies.
 * This container SHALL resolve services using factories, aliases, or delegate resolution
 * to a parent container when configured.
 *
 * @package CoiSA\ErrorHandler\Container
 *
 * @phpstan-import-type FactoryClass from ConfigProvider
 */
final class ErrorHandlerContainer implements ContainerInterface
{
    /**
     * @var null|ContainerInterface a parent container for delegation
     */
    private ?ContainerInterface $container;

    /**
     * @var array<class-string, FactoryClass> invokable factory classes mapped to service IDs
     */
    private array $factories;

    /**
     * @var array<class-string, class-string> aliases mapped to their target service IDs
     */
    private array $aliases;

    /**
     * @var array<string, mixed> cached values returned by service factories or aliases
     */
    private array $instances = [];

    /**
     * Constructs an ErrorHandlerContainer.
     *
     * @param null|ContainerInterface $container an optional parent container for delegation
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
     * @param string $id the identifier of the entry to look for
     *
     * @return bool true if the service ID can be resolved, false otherwise
     */
    public function has(string $id): bool
    {
        return ($this->container && $this->container->has($id))
            || \array_key_exists($id, $this->aliases)
            || \array_key_exists($id, $this->factories);
    }

    /**
     * Retrieves a service instance by its identifier.
     *
     * This method SHALL resolve the service by:
     * - Delegating to the parent container (if available)
     * - Using an alias to resolve the actual service
     * - Instantiating via a factory
     *
     * @param string $id the identifier of the entry to retrieve
     *
     * @return mixed the resolved service instance
     *
     * @throws ContainerException if an error occurs during instantiation
     * @throws NotFoundException  if no entry was found for the given identifier
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
     * @param string $id the identifier of the entry to resolve
     *
     * @return callable a callable factory responsible for creating the service instance
     *
     * @throws NotFoundException if no factory or alias is found for the given identifier
     */
    private function getFactory(string $id): callable
    {
        if (!$this->has($id)) {
            throw new NotFoundException(\sprintf('Factory for class %s was not found', $id));
        }

        if (isset($this->aliases[$id])) {
            return new Factory\AliasFactory($this->aliases[$id]);
        }

        return new $this->factories[$id]();
    }
}
