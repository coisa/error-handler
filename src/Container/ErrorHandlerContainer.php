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
     * @var array
     */
    private $factories = [
        // @TODO add object factories
    ];

    /**
     * @param string $id
     *
     * @return bool
     */
    public function has($id)
    {
        return \array_key_exists($id, $this->factories);
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
        try {
            return ($this->getFactory($id))($this);
        } catch (Exception\NotFoundException $notFoundException) {
            throw $notFoundException;
        } catch (\Throwable $throwable) {
            throw Exception\ContainerException::createFromThrowable($throwable);
        }
    }

    /**
     * @param string $id
     * @param string $factory
     *
     * @return $this
     */
    public function setFactory(string $id, string $factory): self
    {
        $this->factories[$id] = $factory;

        return $this;
    }

    /**
     * @param string $id
     *
     * @throws Exception\NotFoundException
     *
     * @return mixed
     */
    public function getFactory(string $id)
    {
        if (false === $this->has($id)) {
            throw new Exception\NotFoundException(\sprintf('Factory for class %s was not found', $id));
        }

        return $this->factories[$id];
    }
}
