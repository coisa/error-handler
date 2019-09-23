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

namespace CoiSA\ErrorHandler\Container\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Class ContainerException
 *
 * @package CoiSA\ErrorHandler\Container\Exception
 */
class ContainerException extends \Exception implements ContainerExceptionInterface
{
    /**
     * @param \Throwable $throwable
     *
     * @return ContainerException
     */
    public static function createFromThrowable(\Throwable $throwable): self
    {
        return new self(
            $throwable->getMessage(),
            $throwable->getCode(),
            $throwable
        );
    }
}
