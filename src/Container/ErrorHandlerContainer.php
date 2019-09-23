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

use Psr\Container\ContainerInterface;

/**
 * Class ErrorHandlerContainer
 *
 * @package CoiSA\ErrorHandler\Container
 */
final class ErrorHandlerContainer implements ContainerInterface
{
    /**
     * @var null|ContainerInterface
     */
    private $container;

    /**
     * @var string[]
     */
    private $factories;

    /**
     * @var object[]
     */
    private $instances;

    /**
     * ErrorHandlerContainer constructor.
     *
     * @param null|ContainerInterface $container
     */
    public function __construct(ContainerInterface $container = null)
    {
        $this->factories = (new ConfigProvider())->getFactories();
        $this->container = $container;
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    public function has($id)
    {
        return ($this->container && $this->container->has($id))
            || \array_key_exists($id, $this->factories);
    }

    /**
     * @param string $id
     *
     * @throws Exception\ContainerException
     * @throws Exception\NotFoundException
     *
     * @return mixed
     */
    public function get($id)
    {
        if ($this->container && $this->container->has($id)) {
            return $this->container->get($id);
        }

        if (!isset($this->instances[$id])) {
            try {
                $this->instances[$id] = ($this->getFactory($id))($this);
            } catch (Exception\NotFoundException $notFoundException) {
                throw $notFoundException;
            } catch (\Throwable $throwable) {
                throw Exception\ContainerException::createFromThrowable($throwable);
            }
        }

        return $this->instances[$id];
    }

    /**
     * @param string $id
     *
     * @throws Exception\NotFoundException
     *
     * @return callable
     */
    private function getFactory(string $id): callable
    {
        if (false === $this->has($id)) {
            throw new Exception\NotFoundException(\sprintf('Factory for class %s was not found', $id));
        }

        $factory = $this->factories[$id];

        return \is_callable($factory) ? $factory : new $factory();
    }
}
