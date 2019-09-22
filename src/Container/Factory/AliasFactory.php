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

use Psr\Container\ContainerInterface;

/**
 * Class AliasFactory
 *
 * @package CoiSA\ErrorHandler\Container\Factory
 */
final class AliasFactory
{
    /**
     * @var string
     */
    private $alias;

    /**
     * AliasFactory constructor.
     *
     * @param string $alias
     */
    public function __construct(string $alias)
    {
        $this->alias = $alias;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return mixed
     */
    public function __invoke(ContainerInterface $container)
    {
        return $container->get($this->alias);
    }
}
